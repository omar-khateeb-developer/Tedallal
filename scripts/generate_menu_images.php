<?php

$dirStorage = __DIR__ . '/../storage/app/public/menu/items';
$dirPublic  = __DIR__ . '/../public/storage/menu/items';

if (!is_dir($dirStorage)) {
    mkdir($dirStorage, 0755, true);
}
if (!is_dir($dirPublic)) {
    mkdir($dirPublic, 0755, true);
}

// قائمة الأصناف مع صورها المخصصة عالية الجودة من Unsplash
$items = [
    // المقبلات الباردة
    'hummus'               => 'photo-1541518763669-27fef04b14ea', // حمص بالطحينة
    'mutabbal'             => 'photo-1540420773420-3366772f4999', // متبل باذنجان
    'tabbouleh'            => 'photo-1512621776951-a57141f2eefd', // تبولة
    'fattoush'             => 'photo-1540189549336-e6e99c3679fe', // فتوش
    'grape_leaves'         => 'photo-1541544741938-0af808871cc0', // ورق عنب
    'labneh'               => 'photo-1488477181946-6428a0291777', // لبنة بالزيت

    // المقبلات الساخنة
    'fried_kibbeh'         => 'photo-1529042410759-befb1204b468', // كبة مقلية
    'spinach_fatayer'      => 'photo-1509722747041-616f39b57569', // فطائر سبانخ
    'meat_samosa'          => 'photo-1601050690597-df0568f70950', // سمبوسة لحم
    'cheese_samosa'        => 'photo-1601050690117-94f5f6fa8bd7', // سمبوسة جبنة
    'grilled_halloumi'     => 'photo-1528736235302-52922df5c122', // حلوم مشوي
    'batata_harra'         => 'photo-1573080496219-bb080dd4f877', // بطاطا حرة

    // المشاوي
    'mixed_grill'          => 'photo-1555939594-58d7cb561ad1', // مشكل مشاوي
    'shish_tawook'         => 'photo-1599488615731-7e5c2823ff28', // شيش طاووق
    'meat_kebab'           => 'photo-1529193591184-b1d58069ecdd', // كباب لحم
    'lamb_chops'           => 'photo-1544025162-d76694265947', // ريش غنم
    'grilled_kofta'        => 'photo-1603048588665-791ca8aea617', // كفتة مشوية
    'whole_grilled_chicken'=> 'photo-1598103442097-8b74394b95c6', // دجاج مشوي كامل

    // الأطباق الرئيسية
    'mansaf'               => 'photo-1589302168068-964664d93dc0', // منسف أردني
    'chicken_kabsa'        => 'photo-1563379091339-03b21ab4a4f8', // كبسة دجاج
    'lamb_kabsa'           => 'photo-1544025162-d76694265947', // كبسة لحم
    'lamb_mandi'           => 'photo-1544025162-d76694265947', // مندي لحم
    'chicken_mandi'        => 'photo-1563379091339-03b21ab4a4f8', // مندي دجاج
    'chicken_madhbi'       => 'photo-1598103442097-8b74394b95c6', // مظبي دجاج
    'mufattah'             => 'photo-1544025162-d76694265947', // مفطح لحم
    'shawarma_arabi'       => 'photo-1529006557810-274b9b2fc783', // شاورما عربي
    'fatteh_hummus'        => 'photo-1541518763669-27fef04b14ea', // فتة حمص
    'musakhan'             => 'photo-1598103442097-8b74394b95c6', // مسخن فلسطيني
    'makloubeh'            => 'photo-1589302168068-964664d93dc0', // مقلوبة

    // أطباق حلبية وخليجية خاصة
    'muhammara'            => 'photo-1540420773420-3366772f4999', // محمرة بالجوز
    'cherry_kebab'         => 'photo-1555939594-58d7cb561ad1', // كباب بالكرز
    'aleppo_kebab'         => 'photo-1529193591184-b1d58069ecdd', // كباب حلبي
    'kibbeh_safarjaliyeh'  => 'photo-1529042410759-befb1204b468', // كبة سفرجلية
    'kibbeh_labniyeh'      => 'photo-1488477181946-6428a0291777', // كبة لبنية
    'grilled_kibbeh'       => 'photo-1529042410759-befb1204b468', // كبة مشوية
    'green_salad'          => 'photo-1540420773420-3366772f4999', // سلطة خضراء
    'yogurt_salad'         => 'photo-1488477181946-6428a0291777', // سلطة زبادي
    'wheat_soup'           => 'photo-1547592166-23ac45744acd', // شوربة حب
    'foul'                 => 'photo-1541518763669-27fef04b14ea', // فول بالسمن
    'meat_tikka'           => 'photo-1544025162-d76694265947', // لحم تكة
    'chicken_tikka'        => 'photo-1599488615731-7e5c2823ff28', // دجاج تكة
    'grilled_shrimp'       => 'photo-1565680018434-b513d5e5fd47', // ربيان مشوي
    'grilled_fish'         => 'photo-1519708227418-c8fd9a32b7a2', // سمك مشوي

    // المشروبات
    'lemon_mint'           => 'photo-1513558161293-cdaf765ed2fd', // عصير ليمون بالنعناع
    'orange_juice'         => 'photo-1613478223719-2ab802602423', // عصير برتقال
    'mango_juice'          => 'photo-1546173159-315724a31696', // عصير مانجو
    'pomegranate_juice'    => 'photo-1551024709-8f23befc6f87', // عصير رمان
    'red_tea'              => 'photo-1576092768241-dec231879fc3', // شاي أحمر
    'karak_tea'            => 'photo-1576092768241-dec231879fc3', // شاي كرك
    'arabic_coffee'        => 'photo-1514432324607-a09d9b4aefdd', // قهوة عربية
    'turkish_coffee'       => 'photo-1509042239860-f550ce710b93', // قهوة تركية
    'jallab'               => 'photo-1551024709-8f23befc6f87', // جلاب
    'ayran'                => 'photo-1550583724-b2692b85b150', // عيران
    'mineral_water'        => 'photo-1548839140-29a749e1bc4e', // ماء معدنية

    // الحلويات
    'knafeh'               => 'photo-1576618148400-f54bed99fcfd', // كنافة نابلسية
    'baklava'              => 'photo-1519869325930-281384150729', // بقلاوة
    'harissa'              => 'photo-1578985545062-69928b1d9587', // هريسة / بسبوسة
    'mahalabia'            => 'photo-1505252585461-04db1eb84625', // مهلبية
    'awameh'               => 'photo-1587314168485-3236d6710814', // عوامة / لقيمات
    'karabij'              => 'photo-1519869325930-281384150729', // كرابيج حلب
    'stuffed_dates'        => 'photo-1505252585461-04db1eb84625', // تمر محشي
    'arabic_icecream'      => 'photo-1505252585461-04db1eb84625', // آيس كريم عربي
    'cream_honey'          => 'photo-1488477181946-6428a0291777', // قشطة بالعسل
];

echo "جاري تنزيل وتحسين صور الأصناف للموبايل (500x500 @ 75% quality)...\n";

$context = stream_context_create([
    'http' => [
        'timeout' => 15,
        'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
    ],
    'ssl' => [
        'verify_peer' => false,
        'verify_peer_name' => false,
    ],
]);

$successCount = 0;
$failCount = 0;

foreach ($items as $slug => $photoId) {
    $targetFileStorage = "$dirStorage/$slug.jpg";
    $targetFilePublic  = "$dirPublic/$slug.jpg";

    $url = "https://images.unsplash.com/{$photoId}?auto=format&fit=crop&w=500&h=500&q=75";

    $imgData = @file_get_contents($url, false, $context);

    if ($imgData !== false && strlen($imgData) > 1000) {
        // ضغط إضافي للتأكد من حجم مثالي للموبايل (30-50KB)
        $imgResource = @imagecreatefromstring($imgData);
        if ($imgResource) {
            imagejpeg($imgResource, $targetFileStorage, 75);
            imagejpeg($imgResource, $targetFilePublic, 75);
            imagedestroy($imgResource);
            $sizeKb = round(filesize($targetFileStorage) / 1024, 1);
            echo " [OK] $slug.jpg ({$sizeKb} KB)\n";
            $successCount++;
            continue;
        }
    }

    // إذا تعذر التنزيل، إنشاء بطاقة بديلة أنيقة بواسطة GD
    $img = imagecreatetruecolor(500, 500);
    $bg = imagecolorallocate($img, 245, 158, 11); // Amber
    $textColor = imagecolorallocate($img, 255, 255, 255);
    imagefilledrectangle($img, 0, 0, 500, 500, $bg);
    imagestring($img, 5, 180, 240, $slug, $textColor);
    imagejpeg($img, $targetFileStorage, 75);
    imagejpeg($img, $targetFilePublic, 75);
    imagedestroy($img);

    echo " [FALLBACK] $slug.jpg\n";
    $failCount++;
}

echo "\nتم الانتهاء بنجاح: $successCount تم تنزيلها وتوليدها، $failCount بديلة.\n";
