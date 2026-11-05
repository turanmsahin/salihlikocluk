<?php
declare(strict_types=1);

/**
 * Salihli Koçluk - SEO & GEO Uyumlu Yapay Zekâ Blog İçerik Motoru
 */

function generate_ai_blog_article(array $params): array
{
    $topic = trim((string) ($params['topic'] ?? ''));
    if ($topic === '') {
        $topic = 'TYT ve AYT Netlerini Artırmanın Yolları';
    }

    $categoryName = trim((string) ($params['category_name'] ?? 'YKS Koçluğu'));
    $categoryId = (int) ($params['category_id'] ?? 1);
    $audience = trim((string) ($params['audience'] ?? setting('ai_default_audience', 'YKS & LGS Öğrencileri')));
    $tone = trim((string) ($params['tone'] ?? setting('ai_default_tone', 'Samimi, Profesyonel & Sonuç Odaklı')));
    $includeLocal = !empty($params['include_local']) || setting('ai_default_local', '1') === '1';
    $includeFaq = !empty($params['include_faq']) || setting('ai_default_faq', '1') === '1';
    $includeTldr = !empty($params['include_tldr']) || setting('ai_default_tldr', '1') === '1';

    $provider = trim(setting('ai_provider', 'gemini'));

    // 1. Google Gemini API
    if ($provider === 'gemini') {
        $geminiKey = trim(setting('gemini_api_key', ''));
        $geminiModel = trim(setting('gemini_model', 'gemini-2.5-flash'));
        if ($geminiKey !== '') {
            $geminiResult = call_gemini_api($geminiKey, $geminiModel, $topic, $categoryName, $audience, $tone, $includeLocal, $includeFaq, $includeTldr);
            if ($geminiResult !== null) {
                $geminiResult['category_id'] = $categoryId;
                $geminiResult['source'] = 'gemini';
                return $geminiResult;
            }
        }
    }

    // 2. OpenAI API (ChatGPT)
    if ($provider === 'openai') {
        $openAiKey = trim(setting('openai_api_key', ''));
        $openAiModel = trim(setting('openai_model', 'gpt-4o-mini'));
        if ($openAiKey !== '') {
            $openAiResult = call_openai_api($openAiKey, $openAiModel, $topic, $categoryName, $audience, $tone, $includeLocal, $includeFaq, $includeTldr);
            if ($openAiResult !== null) {
                $openAiResult['category_id'] = $categoryId;
                $openAiResult['source'] = 'openai';
                return $openAiResult;
            }
        }
    }

    // 3. API anahtarı yoksa veya çağrı başarısız olursa yüksek kaliteli yerleşik motor çalışır
    $fallbackResult = generate_built_in_article($topic, $categoryName, $audience, $tone, $includeLocal, $includeFaq, $includeTldr);
    $fallbackResult['category_id'] = $categoryId;
    $fallbackResult['source'] = ($provider !== 'builtin' && (setting('gemini_api_key') || setting('openai_api_key'))) ? 'built_in_fallback' : 'built_in';
    return $fallbackResult;
}

/**
 * Google Gemini API Çağrısı
 */
function call_gemini_api(string $apiKey, string $model, string $topic, string $category, string $audience, string $tone, bool $includeLocal, bool $includeFaq, bool $includeTldr): ?array
{
    if (!function_exists('curl_init')) {
        return null;
    }

    if ($model === '') {
        $model = 'gemini-2.5-flash';
    }

    $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . urlencode($model) . ':generateContent?key=' . urlencode($apiKey);

    $localPrompt = $includeLocal 
        ? "Salihli'deki fiziksel ofisimizde yüz yüze takip, Manisa ve çevre ilçeler (Alaşehir, Ahmetli, Kula) öğrencilerine yönelik yerel dokunuşları metne doğal, güven verici şekilde yedir." 
        : "Türkiye genelinde sınav koçluğu perspektifinden yaklaş.";

    $tldrPrompt = $includeTldr 
        ? "Metnin hemen başına '> [!NOTE] Doğrudan Cevap & AI Özeti (TL;DR): ...' şeklinde Google AI Overviews için 45-60 kelimelik hap bir özet ekle." 
        : "";

    $faqPrompt = $includeFaq 
        ? "Metnin sonuna '## Sıkça Sorulan Sorular' başlığı aç ve '### Soru?' formatında Google zengin sonuçları (FAQPage Schema) için en az 3 adet soru-cevap ekle." 
        : "";

    $prompt = <<<PROMPT
Sen Türkiye'de resmi sertifikalı bir YKS ve LGS öğrenci koçusun (Salihli Koçluk).
Aşağıdaki bilgilere göre Google'da 1. sıraya çıkacak, Google AI Overviews (SGE) ve GEO (Generative Engine Optimization) kurallarına tam uyumlu, E-E-A-T (Deneyim, Uzmanlık, Güvenilirlik) standartlarında derinlikli ve akıcı bir blog rehberi yazacaksın.

KONU: {$topic}
KATEGORİ: {$category}
HEDEF KİTLE: {$audience}
ÜSLUP / TON: {$tone}
YEREL DOKUNUŞ: {$localPrompt}
{$tldrPrompt}
{$faqPrompt}

KURALLAR:
1. Başlık ilgi çekici, tıklama oranı (CTR) yüksek ve tam bir SEO başlığı olmalı.
2. Metin Markdown formatında olmalı. Alt başlıklar ## ve ### ile hiyerarşik yapılandırılmalı.
3. Önemli taktikler **kalın** ve - madde imleriyle verilmeli.
4. Yazı en az 700-1000 kelime zenginliğinde, klişelerden uzak, uygulanabilir çalışma taktikleri içermeli.
5. Yalnızca aşağıdaki geçerli JSON formatında yanıt ver, JSON dışında hiçbir şey yazma:

{
  "title": "Tıklama odaklı net SEO başlığı",
  "slug": "url-uyumlu-kisa-slug",
  "excerpt": "Google arama sonuçları ve meta description için 140-160 karakterlik çarpıcı özet",
  "content": "Markdown formatındaki tüm makale içeriği..."
}
PROMPT;

    $payload = [
        'contents' => [
            [
                'parts' => [
                    ['text' => $prompt]
                ]
            ]
        ],
        'generationConfig' => [
            'temperature' => 0.7,
            'responseMimeType' => 'application/json'
        ]
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 25);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || !$response) {
        return null;
    }

    $json = json_decode($response, true);
    $text = $json['candidates'][0]['content']['parts'][0]['text'] ?? '';
    if ($text === '') {
        return null;
    }

    $parsed = json_decode($text, true);
    if (!is_array($parsed) || empty($parsed['title']) || empty($parsed['content'])) {
        return null;
    }

    return [
        'title' => trim((string) $parsed['title']),
        'slug' => slugify((string) ($parsed['slug'] ?? $parsed['title'])),
        'excerpt' => trim((string) ($parsed['excerpt'] ?? '')),
        'content' => trim((string) $parsed['content']),
    ];
}

/**
 * OpenAI API (ChatGPT) Çağrısı
 */
function call_openai_api(string $apiKey, string $model, string $topic, string $category, string $audience, string $tone, bool $includeLocal, bool $includeFaq, bool $includeTldr): ?array
{
    if (!function_exists('curl_init')) {
        return null;
    }

    if ($model === '') {
        $model = 'gpt-4o-mini';
    }

    $localPrompt = $includeLocal 
        ? "Salihli'deki fiziksel ofisimizde yüz yüze takip, Manisa ve çevre ilçeler (Alaşehir, Ahmetli, Kula) öğrencilerine yönelik yerel dokunuşları metne doğal yedir." 
        : "Türkiye genelinde sınav koçluğu perspektifinden yaklaş.";

    $prompt = "Konu: {$topic}\nKategori: {$category}\nHedef Kitle: {$audience}\nTon: {$tone}\n{$localPrompt}\n";
    if ($includeTldr) {
        $prompt .= "Metnin başına '> [!NOTE] Doğrudan Cevap & AI Özeti (TL;DR): ...' şeklinde hap özet ekle.\n";
    }
    if ($includeFaq) {
        $prompt .= "Metnin sonuna '## Sıkça Sorulan Sorular' başlığı altında en az 3 soru-cevap ekle.\n";
    }

    $system = "Sen Salihli Koçluk'un resmî sınav koçusun. Yalnızca şu JSON şemasında yanıt ver: {\"title\":\"...\",\"slug\":\"...\",\"excerpt\":\"...\",\"content\":\"Markdown metni...\"}";

    $payload = [
        'model' => $model,
        'messages' => [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $prompt]
        ],
        'response_format' => ['type' => 'json_object'],
        'temperature' => 0.7
    ];

    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 25);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || !$response) {
        return null;
    }

    $json = json_decode($response, true);
    $rawContent = $json['choices'][0]['message']['content'] ?? '';
    $parsed = json_decode($rawContent, true);
    if (!is_array($parsed) || empty($parsed['title']) || empty($parsed['content'])) {
        return null;
    }

    return [
        'title' => trim((string) $parsed['title']),
        'slug' => slugify((string) ($parsed['slug'] ?? $parsed['title'])),
        'excerpt' => trim((string) ($parsed['excerpt'] ?? '')),
        'content' => trim((string) $parsed['content']),
    ];
}

/**
 * Canlı API Bağlantı Testi (Google Gemini veya OpenAI)
 */
function test_ai_connection(string $provider, string $apiKey, string $model): array
{
    $apiKey = trim($apiKey);
    if ($apiKey === '') {
        return ['success' => false, 'message' => 'Lütfen bir API anahtarı girin.'];
    }

    if (!function_exists('curl_init')) {
        return ['success' => false, 'message' => 'Sunucunuzda cURL fonksiyonu aktif değil.'];
    }

    if ($provider === 'gemini') {
        if ($model === '') $model = 'gemini-2.5-flash';
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . urlencode($model) . ':generateContent?key=' . urlencode($apiKey);
        $payload = [
            'contents' => [
                ['parts' => [['text' => 'Test bağlantısı. Sadece "OK" yanıtı ver.']]]
            ]
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            return ['success' => false, 'message' => 'Bağlantı hatası: ' . $curlError];
        }

        if ($httpCode === 200) {
            return ['success' => true, 'message' => "✓ Bağlantı başarılı! {$model} API anahtarınız aktif ve çalışıyor."];
        }

        $errJson = json_decode((string)$response, true);
        $msg = $errJson['error']['message'] ?? "HTTP {$httpCode} hatası alındı.";
        return ['success' => false, 'message' => "Bağlantı başarısız: {$msg}"];
    }

    if ($provider === 'openai') {
        if ($model === '') $model = 'gpt-4o-mini';
        $payload = [
            'model' => $model,
            'messages' => [['role' => 'user', 'content' => 'ping']],
            'max_tokens' => 5
        ];

        $ch = curl_init('https://api.openai.com/v1/chat/completions');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            return ['success' => false, 'message' => 'Bağlantı hatası: ' . $curlError];
        }

        if ($httpCode === 200) {
            return ['success' => true, 'message' => "✓ Bağlantı başarılı! OpenAI ({$model}) API anahtarınız aktif."];
        }

        $errJson = json_decode((string)$response, true);
        $msg = $errJson['error']['message'] ?? "HTTP {$httpCode} hatası alındı.";
        return ['success' => false, 'message' => "Bağlantı başarısız: {$msg}"];
    }

    return ['success' => false, 'message' => 'Bilinmeyen sağlayıcı.'];
}

/**
 * Yerleşik Akıllı İçerik & Şablon Motoru (100% Bağımsız ve Çalışır)
 */
function generate_built_in_article(string $topic, string $category, string $audience, string $tone, bool $includeLocal, bool $includeFaq, bool $includeTldr): array
{
    $cleanTopic = htmlspecialchars($topic, ENT_QUOTES, 'UTF-8');
    $title = $topic . ': Adım Adım Başarı & Strateji Rehberi';
    $slug = slugify($topic);
    $excerpt = "{$cleanTopic} konusunda hedeflediğiniz başarıya ulaşmanın kanıtlanmış yolları, stratejik çalışma planı ve profesyonel koçluk tavsiyeleri.";

    $localSection = '';
    if ($includeLocal) {
        $localSection = <<<MARKDOWN

## 📍 Salihli'de Yüz Yüze Takip ile Hedefinize Adım Adım Ulaşın

Online içerikler ve video dersler rehberlik sunsa da, sınav hazırlığının en kritik anahtarı **düzenli denetim, hesap verebilirlik ve birebir yüz yüze takip** mekanizmasıdır.

Salihli merkez ofisimizde; Alaşehir, Ahmetli, Kula ve çevre bölgelerden gelen öğrencilerimizle yürüttüğümüz çalışma modelinde:
- **Haftalık Yüz Yüze Seanslar:** Hedeflenen soru sayısı, bitirilen konular ve çalışma saatleri birebir incelenir.
- **Kişiye Özel Çözüm Masası:** Zorlanılan derslerde eksik kapatma programı haftalık olarak yeniden kalibre edilir.
- **Veli Bilgilendirme Raporu:** Öğrencinin gelişim grafiği düzenli olarak aileyle paylaşılır, sınav kaygısı ev ortamına yansımadan çözülür.
MARKDOWN;
    }

    $tldrBlock = '';
    if ($includeTldr) {
        $tldrBlock = "> [!NOTE] Doğrudan Cevap & AI Özeti (TL;DR): {$cleanTopic} sürecinde en hızlı ilerleme; plansız soru çözmek yerine geçmiş deneme hatalarını kategorize edip haftalık mikro hedefler koymaktan geçer. Doğru kaynak seçimi ve düzenli koçluk takibiyle 4-6 hafta içinde belirgin bir net artışı yakalanabilir.\n\n";
    }

    $faqBlock = '';
    if ($includeFaq) {
        $faqBlock = <<<MARKDOWN

## Sıkça Sorulan Sorular

### Bu stratejiyi uygulayarak ne kadar sürede net artışı görebilirim?
Düzenli ve disiplinli bir programla genellikle 3 ila 5 haftalık periyotta deneme netlerinde kalıcı bir yükselme gözlemlenir. İlk 2 hafta genellikle eksik konuların kapanma evresidir.

### Kendi başıma çalışırken motivasyonumu nasıl yüksek tutabilirim?
Büyük hedefleri (örn: 100 net) haftalık küçük hedeflere (örn: bu hafta Problemlerden +2 net) bölmek motivasyonu diri tutar. Düzenli koçluk görüşmeleri ise tökezleme anlarında toparlanmayı hızlandırır.

### Salihli ofisinizde ön görüşme ve seviye analizi nasıl yapılıyor?
Fiziksel ofisimizde öğrencimizin son deneme sonuçları ve çalışma geçmişi üzerinden yaklaşık 40 dakikalık ücretsiz bir tanışma ve yol haritası seansı gerçekleştiriyoruz.
MARKDOWN;
    }

    $content = <<<MARKDOWN
{$tldrBlock}Sınav hazırlık sürecinde her adayın karşılaştığı en kritik yol ayrımlarından biri **{$cleanTopic}** meselesidir. Hedefiniz ister YKS'de derece yapmak, ister LGS'de nitelikli bir liseye yerleşmek olsun; başarı yalnızca çok çalışmaktan değil, **veriye dayalı ve stratejik çalışmaktan** geçer.

Bu kapsamlı rehberde; masanın başına oturduğunuz andan deneme salonundan çıkışınıza kadar uygulayabileceğiniz somut, kanıtlanmış adımları bir araya getirdik.

---

## 1. Mevcut Durumu Teşhis Etmeden İlerleme Sağlanamaz

Bir hekime gittiğinizde teşhis konulmadan ilaç yazılmadığı gibi, ders çalışma sürecinde de mevcut net ve eksik analizi yapılmadan gelişim sağlanamaz:

- **Hata Günlüğü Tutun:** Yanlış yapılan sorular "dikkat hatası" denilip geçilmemelidir. Bilgi eksikliği mi, zaman yönetimi mi, yoksa soru kökünü yanlış okuma mı olduğu not edilmelidir.
- **Konu Eleme Piramidi:** Sınavda en çok soru çıkan ama sizin en çok zorlandığınız 3 ana konuyu belirleyin. İlk haftanın odak noktası bu konular olmalıdır.
- **Zaman Baskısıyla Çalışma:** Evde soru çözerken mutlaka kronometre kullanın. Sınav anındaki stresin %70'i süre baskısından kaynaklanır.

## 2. Pomodoro ve Blok Çalışma: Masada Verimli Kalmanın Formülü

Saatlerce masada oturup verim alamamak, adayların en sık yaşadığı tükenmişlik nedenidir. Çalışma ritminizi şu şekilde yapılandırın:

1. **50 Dakika Odaklanma + 10 Dakika Mola:** Telefonu başka bir odaya bırakarak yalnızca tek bir derse odaklanın.
2. **Günde En Fazla 3 Farklı Branş:** Zihni aşırı dağıtmadan, bir sayısal ve bir sözel dersi kombine ederek çalışın.
3. **Akşam 30 Dakikalık Tekrar:** Günün sonunda çözülen zor soruların çözümlerini tekrar gözden geçirin. Zihin bilgiyi uykuda pekiştirir.

## 3. Deneme Sınavları Bir Sonuç Değil, Gelişim Pusulasıdır

Deneme sınavı neti moral bozmak için değil, önümüzdeki 7 günün çalışma programını oluşturmak için bir veridir:

- Netiniz düşük geldiyse bu bir kayıp değil, eksiklerinizi sınavdan önce fark ettiğiniz için bir fırsattır.
- Netleriniz yükseldiğinde rehavete kapılmadan ritmi korumak gerekir.
- Her denemeden sonra en az 45 dakika "Deneme Otopsisi" yapılmalıdır.
{$localSection}
---
{$faqBlock}
MARKDOWN;

    return [
        'title' => $title,
        'slug' => $slug,
        'excerpt' => $excerpt,
        'content' => $content,
    ];
}

