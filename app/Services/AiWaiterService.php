<?php

namespace App\Services;

use App\Models\MenuItem;
use App\Models\Restaurant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiWaiterService
{
    /**
     * معالجة رسالة الزبون وتوليد رد ذكي وتفاعلي مع اقتراحات الأطباق القابلة للإضافة المباشرة.
     *
     * @param Restaurant $restaurant
     * @param string $userMessage
     * @param array $history
     * @return array{reply: string, recommended_items: array}
     */
    public function chat(Restaurant $restaurant, string $userMessage, array $history = []): array
    {
        $availableItems = $restaurant->menuItems()
            ->where('is_available', true)
            ->with('category')
            ->get();

        if ($availableItems->isEmpty()) {
            return [
                'reply' => "أهلاً بك في مطعم {$restaurant->name}! 🌸 نعتذر، لا توجد أصناف متاحة حالياً في المنيو وسنكون سعداء بخدمتك قريباً.",
                'recommended_items' => [],
            ];
        }

        $apiKey = config('services.gemini.api_key');
        $model = config('services.gemini.model', 'gemini-1.5-flash');

        if (!empty($apiKey)) {
            try {
                $response = $this->callGeminiApi($apiKey, $model, $restaurant, $availableItems, $userMessage, $history);
                if (!empty($response['reply'])) {
                    return $response;
                }
            } catch (\Throwable $e) {
                Log::warning('Gemini AI Waiter API call failed, falling back to smart local engine: ' . $e->getMessage());
            }
        }

        // Smart Local Engine (يعمل محلياً وبشكل ذكي ومتنوع عند عدم وجود مفتاح API أو انقطاع النت)
        return $this->smartLocalFallback($restaurant, $availableItems, $userMessage);
    }

    /**
     * استدعاء واجهة برمجة تطبيقات Google Gemini API المجانية.
     */
    protected function callGeminiApi(string $apiKey, string $model, Restaurant $restaurant, Collection $items, string $userMessage, array $history): array
    {
        $currency = $restaurant->currency ?? 'SYP';

        $menuSummary = $items->map(function (MenuItem $item) use ($currency) {
            $cat = $item->category?->name ?? 'عام';
            return "- [ITEM:{$item->id}] {$item->name} (قسم: {$cat}) - السعر: {$item->price} {$currency}." . ($item->description ? " الوصف: {$item->description}" : '');
        })->implode("\n");

        $systemPrompt = <<<PROMPT
أنت «ويتر تدلل الذكي»، مساعد الضيافة الذكي والشخصي لزبائن مطعم "{$restaurant->name}".
عملة المطعم هي: {$currency}.
شخصيتك: ودود، خبير ضيافة، لبق ومرحب، تتحدث بلغة عربية لطيفة ومحفزة.

مهامك الصارمة:
1. إذا كانت رسالة الزبون ترحيباً (مثل: هلا، مرحبا، السلام عليكم)، رحّب به بلباقة واعرض عليه المساعدة دون حشو أطباق إلا إذا طلب.
2. إذا شكرك الزبون أو ودّعك، رُدّ عليه بعبارات الذوق وصحتين وهنا وتمنيات طيبة.
3. إذا سأل الزبون عن صنف معين أو سأل عن خيارات أو وجبات، ساعده باقتراح أصناف متناسقة من منيو المطعم أدناه.
4. عند ترشيح أي صنف، اذكر اسمه وسعره، وضع كود الصنف الخاص به بجانبه بهذا الشكل تماماً: [ITEM:id] ليتمكن الزبون من إضافته للسلة فوراً.
5. لا تقترح أبداً أي صنف غير موجود في قائمة منيو المطعم أدناه.
6. لا تتحدث خارج سياق الطعام والمطعم والضيافة، وكن مختصراً ومباشراً وشهياً في وصفك.

قائمة أطباق منيو مطعم {$restaurant->name} المتاحة حالياً:
{$menuSummary}
PROMPT;

        // تنظيف وبناء تاريخ المحادثة مع الالتزام بمتطلبات Gemini الصارمة (البدء بـ user والتبادل المنتظم)
        $contents = [];
        $sanitizedHistory = [];

        foreach ($history as $msg) {
            if (!empty($msg['role']) && !empty($msg['content'])) {
                $role = ($msg['role'] === 'user') ? 'user' : 'model';
                $sanitizedHistory[] = [
                    'role' => $role,
                    'parts' => [['text' => (string)$msg['content']]],
                ];
            }
        }

        // شرط Gemini: يجب أن تبدأ أول رسالة بدور 'user'
        while (!empty($sanitizedHistory) && $sanitizedHistory[0]['role'] !== 'user') {
            array_shift($sanitizedHistory);
        }

        // شرط Gemini: يجب التبادل بين user و model
        $prevRole = null;
        foreach (array_slice($sanitizedHistory, -6) as $entry) {
            if ($entry['role'] !== $prevRole) {
                $contents[] = $entry;
                $prevRole = $entry['role'];
            }
        }

        if ($prevRole === 'user') {
            $contents[] = [
                'role' => 'model',
                'parts' => [['text' => 'أهلاً بك، يسعدني خدمتك دائماً!']],
            ];
        }

        $contents[] = [
            'role' => 'user',
            'parts' => [['text' => $userMessage]],
        ];

        $apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

        $response = Http::timeout(10)->post($apiUrl, [
            'system_instruction' => [
                'parts' => [
                    ['text' => $systemPrompt]
                ]
            ],
            'contents' => $contents,
            'generationConfig' => [
                'temperature' => 0.7,
                'maxOutputTokens' => 700,
            ],
        ]);

        if (!$response->successful()) {
            throw new \RuntimeException('Gemini API returned status ' . $response->status() . ': ' . $response->body());
        }

        $result = $response->json();
        $rawReply = $result['candidates'][0]['content']['parts'][0]['text'] ?? '';

        return $this->extractRecommendations($rawReply, $items);
    }

    /**
     * محرك التوصيات والردود المحلي الذكي (يعمل بتنوع وتفاعل فائق لتجنب تكرار الإجابات).
     */
    protected function smartLocalFallback(Restaurant $restaurant, Collection $items, string $userMessage): array
    {
        $currency = $restaurant->currency ?? 'SYP';
        $normalized = mb_strtolower(trim($userMessage));

        // 1. فحص التحيات والاستقبال (Greetings)
        $greetings = ['هلا', 'مرحبا', 'اهلين', 'أهلين', 'سلام', 'السلام عليكم', 'صباح الخير', 'مساء الخير', 'هاي', 'أهلاً', 'اهلاً', 'يا هلا', 'نورت', 'هلو', 'مسا الخير', 'صبحكم'];
        foreach ($greetings as $greet) {
            if ($normalized === $greet || str_starts_with($normalized, $greet . ' ') || str_ends_with($normalized, ' ' . $greet)) {
                $welcomeText = "يا هلا ومية مرحبا بضيوفنا الكرام في مطعم {$restaurant->name}! 🌸✨\n\nنورتنا وسعداء جداً بخدمتك اليوم. أنا «ويتر تدلل الذكي»، جاهز لمساعدتك في تصفح المنيو واختيار أشهى الأطباق وتنسيق وجبتك المفضلة.\n\nتفضل باخباري بما تشتهيه اليوم (مشاوي، مقبلات، وجبات خفيفة، مشروبات أو حلويات)، أو اسألني عن أسعار أي صنف، أو اختر من المقترحات السريعة بالأعلى!";
                return [
                    'reply' => $welcomeText,
                    'recommended_items' => [],
                ];
            }
        }

        // 2. فحص الشكر والثناء والمجاملات (Thanks & Praise)
        $thanks = ['شكرا', 'شكراً', 'تسلم', 'يسلمو', 'يعطيك العافية', 'عافية', 'مشكور', 'تمام', 'ممتاز', 'كفو', 'عاشت ايدك', 'مع السلامة', 'باي'];
        foreach ($thanks as $th) {
            if (str_contains($normalized, $th)) {
                $thanksText = "ألف صحة وهنا وعافية على قلبك يا رب! 🌸 يسعدنا دائماً تدليلكم وتقديم أرقى تجربة ضيافة في مطعم {$restaurant->name}.\n\nإذا رغبت في أي طبق إضافي أو تحلية منعشة أو فنجان قهوة، أنا هنا في خدمتك دائماً! ✨";
                return [
                    'reply' => $thanksText,
                    'recommended_items' => [],
                ];
            }
        }

        // 3. فحص السؤال عن هوية ومهمة الويتر (Identity)
        $identities = ['من انت', 'مين انت', 'شو بتعمل', 'شو وظيفتك', 'ما هو دورك', 'مين معي', 'شو انت', 'من أنت'];
        foreach ($identities as $idQuery) {
            if (str_contains($normalized, $idQuery)) {
                $identityText = "أنا «ويتر تدلل الذكي» 👨‍🍳، مساعدك الشخصي وخبير الضيافة في مطعم {$restaurant->name}.\n\nمهمتي أن أسهل عليك اختيار أشهى الأطباق التي تلائم ذوقك وميزانيتك، واقتراح أطباق جانبية ومشروبات متناسقة مع وجبتك، مع إمكانية إضافتها مباشرة لسلة طلباتك بضغطة زر واحدة!";
                return [
                    'reply' => $identityText,
                    'recommended_items' => [],
                ];
            }
        }

        // 4. طلب اقتراحات بديلة أو تجديد الأطباق (Rotation & Alternative)
        $alternatives = ['غيرهن', 'غيرهم', 'غير', 'بديل', 'خيارات ثانية', 'أطباق ثانية', 'اطباق ثانية', 'اقتراح ثاني', 'شي ثاني', 'شي تاني', 'أخرى', 'خيارات أخرى', 'غير ذلك'];
        foreach ($alternatives as $alt) {
            if (str_contains($normalized, $alt)) {
                $randomItems = $items->shuffle()->take(3);
                $replyPrefix = "تكرم عينك! إليك باقة جديدة ومميزة من أشهى أطباقنا في مطعم {$restaurant->name} لتختار منها:";
                return $this->formatRecommendationResponse($replyPrefix, $randomItems, $currency, $items);
            }
        }

        // 5. البحث المباشر باسم الصنف أو المكون (Dish/Ingredient Search)
        $stopWords = ['في', 'من', 'عن', 'شو', 'بدي', 'أريد', 'اريد', 'عندكم', 'عندكن', 'متوفر', 'بكام', 'كم', 'سعر', 'على', 'هذا', 'هذه', 'هل', 'أنا', 'مع', 'لو', 'سمحت', 'ممكن', 'أعطني', 'اعطني', 'طريقة', 'ايش', 'شو'];
        $cleanWords = array_filter(
            preg_split('/[\s\p{P}]+/u', $normalized),
            fn($w) => mb_strlen($w) >= 3 && !in_array($w, $stopWords, true)
        );

        if (!empty($cleanWords)) {
            $matchedByKeyword = $items->filter(function (MenuItem $item) use ($cleanWords) {
                $searchable = mb_strtolower($item->name . ' ' . ($item->description ?? '') . ' ' . ($item->category?->name ?? ''));
                foreach ($cleanWords as $word) {
                    if (str_contains($searchable, $word)) {
                        return true;
                    }
                }
                return false;
            });

            if ($matchedByKeyword->isNotEmpty()) {
                $firstKeyword = reset($cleanWords);
                $replyPrefix = "أهلاً بك! تفضل، إليك أشهى خيارات «{$firstKeyword}» المتوفرة في منيو {$restaurant->name}:";
                return $this->formatRecommendationResponse($replyPrefix, $matchedByKeyword->take(3), $currency, $items);
            }
        }

        // 6. فحص طلبات الميزانية والتوفير والأسعار الاقتصادية (Budget & Cheap)
        if (str_contains($normalized, 'رخيص') || str_contains($normalized, 'اقتصادي') || str_contains($normalized, 'ميزانية') || str_contains($normalized, 'أرخص') || str_contains($normalized, 'ارخص') || str_contains($normalized, 'توفير') || str_contains($normalized, 'سعر قليل') || str_contains($normalized, 'أوفر') || str_contains($normalized, 'خفيف ع الجيب')) {
            $budgetItems = $items->sortBy('price')->take(3);
            $replyPrefix = "يسعدنا تدليلك بأشهى الأطباق وبأفضل الأسعار الاقتصادية! إليك تشكيلة من أشهى أطباقنا التوفيرية والمميزة:";
            return $this->formatRecommendationResponse($replyPrefix, $budgetItems, $currency, $items);
        }

        // 7. فحص طلبات الوجبات العائلية والمشاوي (Family & Combos)
        if (str_contains($normalized, 'عائل') || str_contains($normalized, 'اشخاص') || str_contains($normalized, 'أشخاص') || str_contains($normalized, 'عزومة') || str_contains($normalized, 'لمة') || str_contains($normalized, 'مشاو') || str_contains($normalized, 'سدر') || str_contains($normalized, 'كومبو') || str_contains($normalized, 'وليمة') || str_contains($normalized, 'كباب')) {
            $familyItems = $items->filter(function ($item) {
                $text = mb_strtolower($item->name . ' ' . ($item->description ?? '') . ' ' . ($item->category?->name ?? ''));
                return str_contains($text, 'مشاو') || str_contains($text, 'دجاج') || str_contains($text, 'لحم') || str_contains($text, 'وجب') || str_contains($text, 'كباب') || str_contains($text, 'عائل');
            });
            if ($familyItems->isEmpty()) {
                $familyItems = $items;
            }
            $replyPrefix = "أهلاً ومرحباً بكم! لتناول وجبة غنية ومميزة تجمع العائلة أو الأصدقاء، أعددت لكم هذه التوليفة الشهية:";
            return $this->formatRecommendationResponse($replyPrefix, $familyItems->shuffle()->take(3), $currency, $items);
        }

        // 8. فحص طلبات الحمية والأكل الصحي (Healthy & Diet)
        if (str_contains($normalized, 'دايت') || str_contains($normalized, 'صحي') || str_contains($normalized, 'خفيف') || str_contains($normalized, 'سلط') || str_contains($normalized, 'رجيم') || str_contains($normalized, 'سعرات') || str_contains($normalized, 'كالوري') || str_contains($normalized, 'مشوي') || str_contains($normalized, 'خضار')) {
            $healthyItems = $items->filter(function ($item) {
                $text = mb_strtolower($item->name . ' ' . ($item->description ?? '') . ' ' . ($item->category?->name ?? ''));
                return str_contains($text, 'سلط') || str_contains($text, 'مشوي') || str_contains($text, 'شورب') || str_contains($text, 'خضار') || str_contains($text, 'صحي');
            });
            if ($healthyItems->isEmpty()) {
                $healthyItems = $items;
            }
            $replyPrefix = "اختيار رائع! لخيارات صحية وخفيفة ومغذية من مطبخنا، أنصحك بهذه الأطباق الشهية:";
            return $this->formatRecommendationResponse($replyPrefix, $healthyItems->shuffle()->take(3), $currency, $items);
        }

        // 9. فحص الحلويات والمشروبات (Sweets & Drinks)
        if (str_contains($normalized, 'حلو') || str_contains($normalized, 'عصير') || str_contains($normalized, 'شرب') || str_contains($normalized, 'تحلي') || str_contains($normalized, 'كيك') || str_contains($normalized, 'كنافة') || str_contains($normalized, 'شاي') || str_contains($normalized, 'قهوة') || str_contains($normalized, 'مشروب') || str_contains($normalized, 'بارد') || str_contains($normalized, 'ساخن')) {
            $drinksItems = $items->filter(function ($item) {
                $text = mb_strtolower($item->name . ' ' . ($item->description ?? '') . ' ' . ($item->category?->name ?? ''));
                return str_contains($text, 'عصير') || str_contains($text, 'حلو') || str_contains($text, 'كيك') || str_contains($text, 'شاي') || str_contains($text, 'مشروب') || str_contains($text, 'قهوة') || str_contains($text, 'كنافة');
            });
            if ($drinksItems->isEmpty()) {
                $drinksItems = $items;
            }
            $replyPrefix = "اختيار رائع للتحلية والانتعاش! إليك أفضل المشروبات والحلويات المتاحة في منيو {$restaurant->name}:";
            return $this->formatRecommendationResponse($replyPrefix, $drinksItems->shuffle()->take(3), $currency, $items);
        }

        // 10. طلب أشهر الأطباق أو الأكثر طلباً (Bestsellers / Most Popular)
        if (str_contains($normalized, 'اشهر') || str_contains($normalized, 'أشهر') || str_contains($normalized, 'طلب') || str_contains($normalized, 'شهرة') || str_contains($normalized, 'مميز') || str_contains($normalized, 'توصي') || str_contains($normalized, 'افضل') || str_contains($normalized, 'أفضل')) {
            $replyPrefix = "إليك الأطباق الأكثر شهرة وطلباً التي ينصح بها ضيوفنا وزبائننا دائماً في مطعم {$restaurant->name}:";
            return $this->formatRecommendationResponse($replyPrefix, $items->shuffle()->take(3), $currency, $items);
        }

        // 11. الاقتراح الافتراضي المتنوع (Dynamic Varied Recommendation)
        $variedPrefixes = [
            "أهلاً بك في مطعم {$restaurant->name}! بناءً على رغبتك، يسعدني أن أقترح عليك تشكيلة مختارة من أشهى أطباقنا اليوم:",
            "يسعدنا تدليلك بأشهى نكهات مطعم {$restaurant->name}! إليك بعض من أفضل توصياتنا المختارة لتستمتع بوجبة مميزة:",
            "تشكيلة رائعة ومتنوعة أعددناها لك من منيو مطعم {$restaurant->name} لتجربة طعام استثنائية:",
        ];
        $replyPrefix = $variedPrefixes[array_rand($variedPrefixes)];

        // نقوم بخلط الأصناف عشوائياً حتى لا تتكرر نفس الـ 3 أصناف في كل مرة
        $selectedItems = $items->shuffle()->take(3);

        return $this->formatRecommendationResponse($replyPrefix, $selectedItems, $currency, $items);
    }

    /**
     * صياغة نص الرد وقائمة الأطباق الموصى بها.
     */
    protected function formatRecommendationResponse(string $prefix, Collection $matchedItems, string $currency, Collection $allItems): array
    {
        $lines = [];
        $lines[] = $prefix;
        $lines[] = "";

        foreach ($matchedItems as $item) {
            $desc = $item->description ? " - {$item->description}" : "";
            $lines[] = "✨ **{$item->name}** [ITEM:{$item->id}] بسعر {$item->price} {$currency}{$desc}";
        }

        $lines[] = "";
        $lines[] = "يمكنك الضغط على زر الإضافة بجانب أي صنف أعلاه لإدراجه مباشرة في سلة طلباتك، وصحتين وهنا! 🌸";

        $fullReply = implode("\n", $lines);

        return $this->extractRecommendations($fullReply, $allItems);
    }

    /**
     * استخراج معرفات [ITEM:id] وإرفاق بيانات الأطباق الكاملة لعرض بطاقات الإضافة السريعة.
     */
    protected function extractRecommendations(string $reply, Collection $availableItems): array
    {
        preg_match_all('/\[ITEM:(\d+)\]/', $reply, $matches);
        $extractedIds = !empty($matches[1]) ? array_unique(array_map('intval', $matches[1])) : [];

        $recommendedItems = [];
        if (!empty($extractedIds)) {
            $recommendedItems = $availableItems->whereIn('id', $extractedIds)->values()->map(function (MenuItem $item) {
                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'price' => (float)$item->price,
                    'image' => $item->image,
                    'category_name' => $item->category?->name ?? '',
                ];
            })->toArray();
        }

        // تنظيف وتهذيب علامات [ITEM:id] من نص الرد ليبقى ممتعاً ومقروءاً
        $cleanedReply = preg_replace('/\[ITEM:\d+\]\s*/', '', $reply);

        return [
            'reply' => trim($cleanedReply),
            'recommended_items' => $recommendedItems,
        ];
    }
}
