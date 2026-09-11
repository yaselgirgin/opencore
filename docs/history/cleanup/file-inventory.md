# OpenCart Dosya Envanteri

## Kapsam ve yöntem

Bu envanter, `opencart-4-clean-install` etiketiyle aynı commit'te bulunan temiz
OpenCart 4.1.0.3 kod tabanının statik analiziyle hazırlanmıştır. Git geçmişinde
bu tabandan sonra eklenmiş bir özel iş modülü yoktur. Sınıflandırmada dosya adı
tek başına kullanılmamış; namespace, controller/model/language/template
eşleşmeleri, loader çağrıları, route bağlantıları, event/startup zinciri ve SQL
referansları birlikte değerlendirilmiştir.

Bu çalışma sırasında uygulama ve yapılandırma dosyaları değiştirilmemiş,
veritabanı ve repository dışındaki storage dizini taranmamıştır.

Sınıflar:

- `CORE`: OpenCart çalışma zamanı veya korunması gereken admin/API altyapısı.
- `ECOMMERCE-STOCK`: Temiz OpenCart dağıtımındaki mağaza işlevi.
- `CUSTOM-BUSINESS`: Şirkete özel iş kodu.
- `SHARED`: Hem platform hem e-ticaret akışında kullanılan veya gelecekteki iş
  modüllerinin açıkça ihtiyaç duyabileceği bileşen.
- `UNKNOWN`: Statik kod analiziyle güvenle karar verilemeyen bileşen.

## Kök ve çalışma zamanı

| Yol | Sınıf | Gerekçe |
| --- | --- | --- |
| `index.php` | CORE | Catalog/API uygulamasının bootstrap girişidir; API-only hedefte de gerekir. |
| `cron.php` | SHARED | Ortak cron dispatcher'ıdır ancak çalıştırdığı action değerleri `cron` tablosundan dinamik gelir. |
| `system/startup.php` | CORE | PHP sürüm kontrolü ile autoloader ve temel helper bootstrap'ını kurar. |
| `system/framework.php` | CORE | Registry, loader, event, request, response, DB, session, cache, template ve route dispatch zinciridir. |
| `system/vendor.php` | CORE | OpenCart'ın paketlenmiş üçüncü taraf sınıf yükleme haritasıdır. |
| `system/config/default.php` | CORE | Ortak çalışma zamanı ayarlarını tanımlar. |
| `system/config/admin.php` | CORE | Admin pre-action, varsayılan route ve çekirdek event zincirini tanımlar. |
| `system/config/catalog.php` | SHARED | API bootstrap'ı burada; mevcut pre-action listesindeki customer/currency/tax/marketing ve theme event'leri mağaza bağımlılığıdır. |
| `config.php`, `ocadmin/config.php` | UNKNOWN | Ortam ve sır değerleri içerir; Git tarafından ignore edilir ve bu görevde değiştirilmez. |
| `.htaccess.txt`, `error.html`, `php.ini` | SHARED | Web sunucusu/hata çalışma desteğidir; hedef deployment kararı verilmeden silinmez. |
| `robots.txt` | ECOMMERCE-STOCK | Storefront crawler politikasıdır; API-only hedefte işlevsizdir. |
| `image/` | SHARED | Stok mağaza görselleri içerir; file manager ve ilerideki iş modülleri de aynı kökü kullanabilir. İçerik bazında ayrıca ayrıştırılmalıdır. |

## System kütüphaneleri

| Yol | Sınıf | Gerekçe |
| --- | --- | --- |
| `system/engine/` | CORE | Registry, Loader, Action, Controller, Model, Proxy ve Event uygulamasıdır. |
| `system/helper/` | CORE | Genel doğrulama, filtre ve UTF-8 yardımcıları çalışma zamanınca kullanılır. |
| `system/library/cache*` | CORE | Korunan cache altyapısı ve sürücüleridir. |
| `system/library/db*` | CORE | Korunan veritabanı soyutlaması ve sürücüleridir. |
| `system/library/session*` | CORE | Admin oturumu ve API çalışma zamanı için gereklidir. |
| `system/library/request.php`, `response.php`, `url.php` | CORE | Routing ve HTTP giriş/çıkış altyapısıdır. |
| `system/library/language.php`, `document.php`, `template*` | CORE | Admin dil, layout ve Twig entegrasyonudur. |
| `system/library/log.php`, `curl.php` | CORE | Loglama ve ortak dış HTTP erişim altyapısıdır. |
| `system/library/mail*`, `image.php` | SHARED | Stok e-ticaret kullanır; özel iş bildirimleri, belge ve görsel işlemleri de kullanabilir. |
| `system/library/cart/user.php` | CORE | Dizinin adına rağmen admin kullanıcı/permission nesnesidir ve admin startup tarafından kullanılır. |
| `system/library/cart/api.php` | SHARED | Mevcut OpenCart API kimlik nesnesidir; hedef API authentication kararı verilmeden kaldırılmaz. |
| `system/library/cart/cart.php`, `customer.php` | ECOMMERCE-STOCK | Sepet ve storefront müşteri oturum durumunu yönetir. |
| `system/library/cart/currency.php` | SHARED | Storefront fiyatlandırmasında kullanılır; iş uygulamaları para birimi kullanabileceği için ayrıştırılmadan silinmez. |
| `system/library/cart/length.php`, `weight.php` | SHARED | Ürün/kargo ölçülerinde kullanılır; ERP stok/lojistik gereksinimi doğrulanmalıdır. |
| `system/library/cart/tax.php` | ECOMMERCE-STOCK | Storefront vergi ve geo-zone hesaplamasına bağlıdır. |

## Admin uygulaması (`ocadmin/`)

Controller sınıflarıyla aynı route grubundaki model, language ve Twig dosyaları
aksi ayrıca belirtilmedikçe aynı sınıftadır.

| Alan/yol | Sınıf | Gerekçe |
| --- | --- | --- |
| `ocadmin/index.php` | CORE | Fiziksel `ocadmin/` uygulamasının bootstrap girişidir. |
| `ocadmin/controller/common/` | CORE | Login/logout, header/footer, column, error çevresi, pagination, file manager ve dashboard kabuğudur. Dashboard içeriği dinamiktir. |
| `ocadmin/model/user/`, `controller/user/`, eş language/template | CORE | Admin kullanıcı, user group, access/modify permission, profil ve API user yönetimidir. |
| `ocadmin/controller/startup/`, eş language | CORE | Admin session, login, permission, event ve uygulama bootstrap zinciridir. |
| `ocadmin/controller/error/`, eş language/template | CORE | Admin exception, not-found ve permission cevaplarıdır. |
| `ocadmin/model/setting/setting.php`, `event.php`, `startup.php` | CORE | Ayar, event ve startup kayıt altyapısıdır. |
| `ocadmin/model/setting/extension.php`, `module.php`, `modification.php` | SHARED | Platform yükleme/event akışında kullanılır; mevcut kurulu içerik ağırlıkla stok e-ticarettir. |
| `ocadmin/controller/setting/setting.php`, eş language/template | CORE | Korunması istenen ayarlar altyapısıdır. |
| `ocadmin/controller/setting/store.php`, eş model/language/template | SHARED | Çoklu mağaza işlevi stoktur; `store` kaydı startup ve ayarlar tarafından da okunur. |
| `ocadmin/controller/tool/log.php`, `upload.php`, eş model/view/language | CORE | Log ve upload ortak admin araçlarıdır. |
| `ocadmin/controller/tool/backup.php`, `upgrade.php`, `notification.php` | SHARED | Operasyonel araçlardır; ihtiyaç ve güvenlik politikası doğrulanmadan silinmez. |
| `ocadmin/controller/design/translation.php`, eş model/view/language | SHARED | Dil/çeviri altyapısıdır; storefront ve admin birlikte kullanır. |
| `ocadmin/controller/design/layout.php`, `theme.php`, `seo_url.php`, `banner.php` | ECOMMERCE-STOCK | Storefront layout, tema, SEO ve banner yönetimidir. |
| `ocadmin/controller/localisation/language.php`, eş model/view/language | CORE | Admin yerelleştirme altyapısıdır. |
| `ocadmin/controller/localisation/country.php`, `currency.php`, `location.php`, `zone.php`, `address_format.php` | SHARED | Stok checkout kullanır; ERP/CRM adres ve para birimi ihtiyacı da olabilir. |
| `ocadmin/controller/localisation/length_class.php`, `weight_class.php`, `stock_status.php`, `order_status.php` | UNKNOWN | Stok e-ticarettir fakat gelecekteki ERP stok/sipariş kavramlarıyla çakışır. Gereksinim olmadan silinmez. |
| `ocadmin/controller/localisation/geo_zone.php`, `tax_class.php`, `tax_rate.php`, return/subscription status dosyaları | ECOMMERCE-STOCK | Checkout vergi, iade ve abonelik süreçlerine bağlıdır. |
| `ocadmin/controller/catalog/`, `model/catalog/`, eş language/template | ECOMMERCE-STOCK | Product, category, option, attribute, manufacturer, review ve mağaza bilgisi yönetimidir. |
| `ocadmin/controller/customer/`, `model/customer/`, eş language/template | UNKNOWN | Kod temiz OpenCart müşteri alanıdır; ad/adres kavramlarının gelecekte CRM tarafından kullanımı netleştirilmelidir. |
| `ocadmin/controller/sale/`, `model/sale/`, eş language/template | UNKNOWN | Kod stok order/return/subscription alanıdır; ERP sipariş geçiş planı olmadan kaldırılması risklidir. |
| `ocadmin/controller/marketing/`, `model/marketing/`, eş language/template | ECOMMERCE-STOCK | Affiliate, coupon ve OpenCart marketing akışıdır. |
| `ocadmin/controller/cms/`, `model/cms/`, eş language/template | ECOMMERCE-STOCK | Stok storefront article/topic/comment içeriğidir. |
| `ocadmin/controller/report/`, `model/report/`, eş language/template | ECOMMERCE-STOCK | Online kullanıcı ve e-ticaret istatistik rapor kabuğudur. |
| `ocadmin/controller/mail/`, eş language/template | ECOMMERCE-STOCK | Mevcut dokuz handler affiliate/customer/order/return/reward/subscription mail olaylarına bağlıdır. Mail kütüphanesi bununla birlikte silinmez. |
| `ocadmin/controller/extension/` | SHARED | Extension yönetim kabuğudur; category controller'larının çoğu stok e-ticaret extension tiplerini yönetir. |
| `ocadmin/controller/marketplace/`, eş model/language/template | ECOMMERCE-STOCK | OpenCart marketplace, installer, promotion, cron ve modification yönetim ekranlarıdır; event/modification ihtiyacı ayrıca ayrıştırılmalıdır. |
| `ocadmin/controller/event/modification.php`, `language.php`, `debug.php` | CORE | Config event zincirinden çağrılan çalışma zamanı uyarlamalarıdır. |
| `ocadmin/controller/event/currency.php`, `statistics.php` | ECOMMERCE-STOCK | Para kuru ve mağaza istatistik yan etkileridir. |

## Catalog uygulaması

| Alan/yol | Sınıf | Gerekçe |
| --- | --- | --- |
| `catalog/controller/startup/setting.php`, `session.php`, `language.php`, `application.php`, `extension.php`, `startup.php`, `error.php`, `event.php`, `api.php`, `authorize.php` | CORE | API-only catalog bootstrap ve dispatch için gereken başlangıç zinciridir. |
| `catalog/controller/startup/seo_url.php`, `customer.php`, `currency.php`, `tax.php`, `marketing.php`, `sass.php`, `maintenance.php` | ECOMMERCE-STOCK | Storefront SEO, müşteri, fiyat/vergi, tracking, tema derleme ve bakım sayfası akışıdır. |
| `catalog/controller/error/` | CORE | API için JSON'a dönüştürülmesi gerekse de route hata altyapısı korunmalıdır. |
| `catalog/controller/api/` ve eş model/language | ECOMMERCE-STOCK | Mevcut affiliate, cart, customer, order, address, payment, shipping ve subscription API'leridir; özel API yoktur. |
| `catalog/controller/common/`, eş language/template | ECOMMERCE-STOCK | Storefront header/footer/home/menu/search/cart/layout bölgeleridir. |
| `catalog/controller/account/`, eş model/language/template | ECOMMERCE-STOCK | Storefront müşteri hesabı, wishlist, order, return ve affiliate alanıdır. |
| `catalog/controller/product/`, `model/catalog/`, eş language/template | ECOMMERCE-STOCK | Storefront ürün, kategori, arama, review ve karşılaştırma alanıdır. |
| `catalog/controller/checkout/`, `model/checkout/`, eş language/template | ECOMMERCE-STOCK | Cart, checkout, payment ve shipping akışıdır. |
| `catalog/controller/information/`, `cms/`, eş model/language/template | ECOMMERCE-STOCK | Storefront bilgi, iletişim, sitemap, blog ve yorum sayfalarıdır. |
| `catalog/controller/mail/`, eş language/template | ECOMMERCE-STOCK | Stok account/order/review/subscription olay e-postalarıdır. |
| `catalog/controller/cron/cron.php` | SHARED | DB'deki aktif cron action'larını dispatch eden ortak controller'dır. |
| `catalog/controller/cron/currency.php`, `gdpr.php`, `subscription.php` | ECOMMERCE-STOCK | Stok para kuru, müşteri GDPR ve subscription görevleridir. |
| `catalog/controller/event/modification.php`, `language.php`, `debug.php` | CORE | Catalog config event zincirinin altyapı handler'larıdır. |
| `catalog/controller/event/theme.php`, `translation.php`, `activity.php`, `statistics.php` | ECOMMERCE-STOCK | Storefront render, müşteri etkinliği ve mağaza istatistik event'leridir. |
| `catalog/controller/tool/upload.php`, `model/tool/upload.php` | SHARED | Storefront kullanır; özel API dosya yüklemeleri de kullanabilir. |
| `catalog/model/setting/`, `design/translation.php`, `localisation/language.php` | SHARED | API bootstrap/ayar/event/dil tarafından kullanılan ortak modellerle storefront ayarları aynı gruptadır. |

## Extension alanı

| Yol | Sınıf | Gerekçe |
| --- | --- | --- |
| `extension/opencart/*/analytics/`, `feed/`, `fraud/`, `payment/`, `shipping/`, `total/` | ECOMMERCE-STOCK | Analytics, ürün feed'i, fraud, ödeme, kargo ve sipariş toplamı extension'larıdır. |
| `extension/opencart/*/dashboard/` | ECOMMERCE-STOCK | Activity, chart, customer, map, online, order, recent ve sale stok widget'larıdır. |
| `extension/opencart/*/module/` | ECOMMERCE-STOCK | Storefront account, category, product, banner, blog ve HTML modülleridir. |
| `extension/opencart/*/report/` | ECOMMERCE-STOCK | Customer/product/order/return/tax/subscription raporlarıdır. |
| `extension/opencart/*/api/coupon.php`, `reward.php` | ECOMMERCE-STOCK | Checkout coupon ve reward API uzantılarıdır. |
| `extension/opencart/*/checkout/` | ECOMMERCE-STOCK | Coupon, reward ve shipping checkout parçalarıdır. |
| `extension/opencart/*/currency/` | SHARED | Stok kur sağlayıcılarıdır; şirket içi uygulamanın kur ihtiyacı doğrulanmalıdır. |
| `extension/opencart/*/captcha/basic.php` | SHARED | Storefront formunda kullanılır; admin/API güvenliği için yeniden kullanılabilir. |
| `extension/opencart/*/theme/basic.php` | ECOMMERCE-STOCK | Storefront tema extension'ıdır. |
| `extension/ocmod/` | UNKNOWN | Kaynakta paketlenmiş OCMOD çalışma alanıdır; kurulu modification kayıtları DB sorgulanmadan bilinemez. |

## Özel ve belirsiz alanlar

- `CUSTOM-BUSINESS` olarak doğrulanabilen dosya veya route bulunmamıştır.
- `UNKNOWN`: ortam config'leri, `extension/ocmod/`, admin customer/sale alanları
  ve ERP anlamı taşıyabilecek localization dosyalarıdır.
- `SHARED`: mail, image, currency/ölçü yardımcıları, catalog/admin setting ve
  extension altyapısı, cron dispatcher, upload ve bazı operasyonel admin
  araçlarıdır.

## Bağımlılık notları

- Admin controller'larının büyük bölümü `common/header`, `common/column_left`,
  `common/footer` ve `common/pagination` çağrılarına bağlıdır.
- `common/dashboard`, kurulu dashboard extension'larını DB'den okuyup dinamik
  controller çağırır; yalnız dosya silmek yeterli değildir.
- `system/config/catalog.php` halen customer, currency, tax, marketing, theme ve
  translation storefront zincirini başlatır.
- Twig dosyalarında statik `{% include %}` veya `{% extends %}` referansı
  bulunmamıştır. Layout bileşimi controller'ların yüklediği `header`,
  `column_left`, `footer` değişkenleriyle yapılmaktadır.
- Event, extension, startup ve cron action'larının bir bölümü DB tablolarından
  dinamik okunur; DB sorgulanmadığı için kurulu kayıtlar bu envanterde
  doğrulanamamıştır.
