# www.salihlikocluk.com.tr

Salihli'de YKS koçluğu için bağımsız, PHP 8 ve SQLite tabanlı tanıtım sitesi.
Harici dijital koçluk ürünü bu projeye entegre değildir.

## Gereksinimler

- PHP 8.1 veya üzeri
- PDO SQLite eklentisi
- mbstring eklentisi

## Yerel çalıştırma

Proje klasöründe Pretty / SEO URL desteğiyle çalıştırmak için:

```sh
php -S 127.0.0.1:8085 -t public router.php
```

Siteyi `http://127.0.0.1:8085` adresinde açın. Web kökü `public/` klasörüdür;
temiz bağlantılar (`/yks`, `/tyt`, `/blog`, `/blog/yazi-adi`, `/iletisim`) yerelde `router.php`, canlı sunucuda (cPanel/Apache/LiteSpeed) ise `public/.htaccess` rewrite kuralları ile otomatik çalışır. Veritabanı web kökünün dışında, `storage/site.sqlite` konumunda güvenle saklanır.

## Yönetim Paneli (`/admin/`)

Siteyi tarayıcınızda açtıktan sonra `/admin/` adresinden yönetim paneline erişebilirsiniz:
- **Varsayılan Kullanıcı Adı:** `admin`
- **Varsayılan Parola:** `SalihliKoc2026!`
*(İlk girişinizden sonra paneldeki "Yönetici Parolasını Değiştir" bölümünden kendi şifrenizi belirleyebilirsiniz.)*

Panel üzerinden yönetilebilen bölümler:
1. **👤 Koç Profili:** İsim, unvan, biyografi, eğitim/sertifikalar, telefon, WhatsApp, e-posta, fiziksel ofis lokasyonu ve parola güncelleme.
2. **🎯 SEO Paneli:** Sayfa başlığı eki (suffix), varsayılan meta açıklaması, anahtar kelimeler, Google Search Console doğrulama kodu ve Google Analytics (GA4) izleme kimliği.
3. **🖼️ Slider / Vitrin:** Ana sayfadaki dinamik slaytları ekleme, düzenleme, sıralama, buton belirleme ve yayına alma.
4. **📄 Sayfa Yönetimi:** TYT, AYT, YKS, Mezun, Süreç, Hakkımda, Gizlilik gibi tüm sayfaların başlık, SEO metinleri ve `Başlık | Açıklama` formatında bölümlerini doğrudan düzenleme.
5. **📝 Blog Paneli:** Blog yazısı ekleme, zengin içerik yazma, taslak/yayında durumunu yönetme ve silme.
6. **📬 Ön Görüşme Talepleri:** Formdan gelen başvuruları görüntüleme, tek tıkla arama / WhatsApp mesajı başlatma ve durum takibi (Yeni, Görüşüldü, Tamamlandı).

## Koç ve İletişim Bilgilerini Güncelleme

Site üzerindeki koç profili (isim, unvan, biyografi, eğitim, yaklaşım) ve iletişim kanalları (telefon, WhatsApp, e-posta, Instagram, lokasyon/çalışma modeli) iki farklı yolla yönetilebilir:

1. **Yönetim Panelinden:**
   `/admin/?tab=settings` adresindeki formu doldurup "Bilgileri Kaydet" butonuna tıklayarak.
2. **Terminal / CLI Üzerinden:**
   ```sh
   php bin/update-settings.php coach_name="Turan Yılmaz" phone="0555 123 45 67" whatsapp="05551234567"
   ```
   (Mevcut ayarları listelemek için sadece `php bin/update-settings.php` çalıştırabilirsiniz.)

## İçerik ve kapsam

- Ana sayfa, YKS/TYT/AYT/mezun öğrenci koçluğu, yazılım destekli koçluk,
  veli danışmanlığı, koçluk süreci, hakkımda, blog ve iletişim sayfaları
- Blog yazısı yönetimi ve ön görüşme taleplerini görüntüleme
- Sayfa başlığı/açıklaması, canonical URL ve sitemap
- Form ve admin işlemlerinde CSRF koruması, PDO prepared statements ve parola hash'i
- Ön görüşme taleplerini yönetme ve gerektiğinde silme

Koçun adı, iletişim bilgileri, hizmet/ücret ayrıntıları ve gerçek referanslar
yayın öncesinde doğrulanıp içeriklere eklenmelidir. Henüz doğrulanmamış başarı,
uzmanlık veya 7/24 hizmet iddiaları kullanılmamıştır.

Ön görüşme formu kişisel veri toplar. Canlıya almadan önce gizlilik sayfasındaki
veri sorumlusu/iletişim ve saklama süresi bilgilerini tamamlayın; formu yalnızca
uygun aydınlatma ve veri saklama süreci belirlendikten sonra kullanıma açın.
