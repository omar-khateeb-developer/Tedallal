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
     * معالجة رسالة الزبون وتوليد رد ذكي مع اقتراحات الأطباق القابلة للإضافة المباشرة.
     *
     * @param Restaurant $restaurant
     * @param string $userMessage
     * @param array $history
     * @return array{reply: string, recommended_items: array}
     */
    public function chat(Restaurant $restaurant, string $userMessage, array $history = []): array
    {
        $currency = $restaurant->currency ?? 'SYP';
        $availableItems = $restaurant->menuItems()
            ->where('is_available', true)
            ->with('category')
            ->get();

        if ($availableItems->isEmpty()) {
            return [
                'reply' => "أهلاً بك في مطعم {$restaurant->name}! نعتذر، لا توجد أصناف متاحة حالياً في المنيو وسنكون سعداء بخدمتك قريباً.",
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
                Log::warning('Gemini AI Waiter API call failed, falling back to smart local recommender: ' . $e->getMessage());
            }
        }

        // Smart Local Fallback (يعمل مجاناً وبدون إنترنت في حال عدم توفر مفتاح API)
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
1. ساعد الزبون بلباقة في اختيار الأطباق بناءً على طلبه (سواء سأل عن وجبة عائلية، خيارات صحية، دايت، كيتو، ميزانية محددة، أو أفضل الأطباق).
2. اقترح أصنافاً متناسقة ومكملة لبعضها (مثلاً: طبق رئيسي + مقبلات أو سلطة + مشروب منعش أو تحلية).
3. عند ترشيح أي صنف، اذكر اسمه وسعره، وضع كود الصنف الخاص به بجانبه بهذا الشكل تماماً: [ITEM:id] ليتمكن الزبون من إضافته للسلة فوراً.
4. لا تقترح أبداً أي صنف غير موجود في قائمة منيو المطعم أدناه.
5. لا تتحدث خارج سياق الطعام والمطعم والضيافة، وكن مختصراً ومباشراً وشهياً في وصفك.

قائمة أطباق منيو مطعم {$restaurant->name} المتاحة حالياً:
{$menuSummary}
PROMPT;

        $contents = [];
        
        // Add conversation history
        foreach (array_slice($history, -4) as $msg) {
            if (!empty($msg['role']) && !empty($msg['content'])) {
                $contents[] = [
                    'role' => $msg['role'] === 'user' ? 'user' : 'model',
                    'parts' => [['text' => $msg['content']]],
                ];
            }
        }

        // Add current user message
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
     * محرك التوصيات المحلي الذكي (يعمل تلقائياً عند غياب المفتاح لضمان استمرارية الخدمة 100%).
     */
    protected function smartLocalFallback(Restaurant $restaurant, Collection $items, string $userMessage): array
    {
        $currency = $restaurant->currency ?? 'SYP';
        $normalized = mb_strtolower($userMessage);

        $matchedItems = collect();

        // 1. فحص طلبات الحمية والدايت
        if (str_contains($normalized, 'دايت') || str_contains($normalized, 'صحي') || str_contains($normalized, 'خفيف') || str_contains($normalized, 'سلط')) {
            $matchedItems = $items->filter(function ($item) {
                $text = mb_strtolower($item->name . ' ' . ($item->description ?? '') . ' ' . ($item->category?->name ?? ''));
                return str_contains($text, 'سلط') || str_contains($text, 'مشوي') || str_contains($text, 'شورب') || str_contains($text, 'خضار');
            })->take(3);

            $replyPrefix = "أهلاً بك! لاختيارات صحية وخفيفة من مطبخنا، أنصحك بهذه الأطباق الشهية والمغذية:";
        }
        // 2. فحص طلبات الوجبات العائلية والمشاوي
        elseif (str_contains($normalized, 'عائل') || str_contains($normalized, 'أشخاص') || str_contains($normalized, 'مشاو') || str_contains($normalized, 'لحم') || str_contains($normalized, 'دجاج')) {
            $matchedItems = $items->filter(function ($item) {
                $text = mb_strtolower($item->name . ' ' . ($item->description ?? '') . ' ' . ($item->category?->name ?? ''));
                return str_contains($text, 'مشاو') || str_contains($text, 'دجاج') || str_contains($text, 'لحم') || str_contains($text, 'وجب') || str_contains($text, 'كباب');
            })->take(3);

            $replyPrefix = "أهلاً ومرحباً بكم! لتناول وجبة غنية ومميزة مع العائلة أو الأصدقاء، أعددت لكم هذه التوليفة اللذيذة:";
        }
        // 3. فحص الحلويات والمشروبات
        elseif (str_contains($normalized, 'حلو') || str_contains($normalized, 'عصير') || str_contains($normalized, 'شرب') || str_contains($normalized, 'تحلي')) {
            $matchedItems = $items->filter(function ($item) {
                $text = mb_strtolower($item->name . ' ' . ($item->description ?? '') . ' ' . ($item->category?->name ?? ''));
                return str_contains($text, 'عصير') || str_contains($text, 'حلو') || str_contains($text, 'كيك') || str_contains($text, 'شاي') || str_contains($text, 'مشروب');
            })->take(3);

            $replyPrefix = "اختيار رائع للتحلية والانتعاش! إليك أفضل المشروبات والحلويات المتاحة في منيو {$restaurant->name}:";
        }
        // 4. الاقتراح الافتراضي الأكثر شهرة وتنوعاً
        else {
            $matchedItems = $items->take(3);
            $replyPrefix = "أهلاً بك في مطعم {$restaurant->name}! بناءً على رغبتك، يسعدني أن أقترح عليك تشكيلة مختارة من أشهى أطباقنا اليوم:";
        }

        // إذا لم نجد تطابقاً نأخذ أول أطباق متاحة
        if ($matchedItems->isEmpty()) {
            $matchedItems = $items->take(3);
        }

        $lines = [];
        $lines[] = $replyPrefix;
        $lines[] = "";

        foreach ($matchedItems as $item) {
            $desc = $item->description ? " - {$item->description}" : "";
            $lines[] = "✨ **{$item->name}** [ITEM:{$item->id}] بسعر {$item->price} {$currency}{$desc}";
        }

        $lines[] = "";
        $lines[] = "يمكنك الضغط على زر الإضافة بجانب أي صنف أعلاه لإدراجه مباشرة في سلة طلباتك، وصحتين وهنا! 🌸";

        $fullReply = implode("\n", $lines);

        return $this->extractRecommendations($fullReply, $items);
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
