# OpenCore Route Sözleşmesi ve Tarihsel OpenCart Envanteri

## Güncel app/api Route Sözleşmesi

OpenCore'nın iki sabit HTTP uygulama sınırı vardır:

- Uygulama arayüzü `app/` üzerinden çalışır. Uygulama içi route'lar, örneğin
  `tool/runtime_diagnostics`, `tool/runtime_diagnostics.release`,
  `tool/notification` ve `tool/notification.info`, yalnız bu context'te URL
  builder ile üretilir. Runtime Diagnostics/System Diagnostics ve notification
  bağlantıları `app/` tabanını kullanır; sabit `/admin/` URL'si içermez.
- Makine API'si `api/` üzerinden çalışır. Sağlık denetimi route'u
  `api/system/ping`dir. API, uygulama HTML yönlendirmesini paylaşmaz.
- Zamanlanmış işler HTTP route değildir: kökteki `cron.php`, CLI ile
  `php cron.php [--cron-id=<positive integer>]` biçiminde çalışır ve App
  context'indeki `cron/cron` dispatcher'ını çağırır.

Eski `/admin/` ve `/catalog/` HTTP yolları 404 döndürür; redirect veya
deprecation alias yoktur. Aşağıdaki envanter, kaldırma kararlarının kaynağı olan
OpenCart 4.1.0.4 tarihsel route analizidir; güncel OpenCore route listesi değildir.

## Kapsam

Route'lar controller dosyaları, `system/config/*.php`, `load->controller`, URL
üretimi, model/language loader çağrıları ve cron/event dispatch kodu taranarak
çıkarılmıştır. Noktadan sonraki method çağrıları (`route.method`) aynı controller
route'unun operasyonlarıdır. Veritabanından dinamik yüklenen event, startup,
extension ve cron action değerleri SQL çalıştırılmadığı için ayrıca risk olarak
işaretlenmiştir.

## Admin çekirdek rotaları

Korunması gereken route grupları:

- `common/login`, `common/logout`, `common/forgotten`, `common/authorize`
- `common/header`, `common/footer`, `common/column_left`, `common/pagination`
- `common/dashboard`: kabuk korunur; dinamik stok widget'lar ayrıştırılır.
- `common/filemanager`, `common/language`, `common/security`, `common/developer`
- `error/exception`, `error/not_found`, `error/permission`
- `user/user`, `user/user_group`, `user/user_permission`, `user/profile`, `user/api`
- `setting/setting`
- `tool/log`, `tool/upload`; `tool/backup`, `tool/upgrade`, `tool/notification`
  gereksinim doğrulamasıyla korunur.
- `startup/setting`, `startup/session`, `startup/language`,
  `startup/application`, `startup/extension`, `startup/startup`,
  `startup/error`, `startup/event`, `startup/sass`, `startup/login`,
  `startup/authorize`, `startup/permission`
- `event/modification`, `event/language`, `event/debug`

Admin varsayılan route'u `common/dashboard` olarak
`system/config/admin.php` içinde tanımlıdır.

## Admin e-ticaret rotaları

Temiz OpenCart'a ait kaldırma adayları route grubu bazında:

- `catalog/*`: attribute, attribute_group, category, download, filter,
  filter_group, identifier, information, manufacturer, option, product, review,
  subscription_plan
- `cms/*`: antispam, article, comment, topic
- `customer/*`: address, customer, customer_approval, customer_group,
  custom_field, gdpr. ERP/CRM çakışması nedeniyle kaldırmadan önce ayrıca
  doğrulanmalıdır.
- `sale/*`: order, returns, subscription. ERP sipariş çakışması nedeniyle
  kaldırmadan önce ayrıca doğrulanmalıdır.
- `marketing/*`: affiliate, contact, coupon, marketing
- `report/*`: online, report, statistics
- `mail/*`: affiliate, authorize, customer, forgotten, gdpr, returns, reward,
  subscription, transaction
- `design/banner`, `design/layout`, `design/seo_url`, `design/theme`
- `localisation/geo_zone`, `localisation/tax_class`, `localisation/tax_rate`,
  return ve subscription status route'ları
- `marketplace/*`: api, cron, event, extension, installer, marketplace,
  modification, promotion, startup
- `extension/analytics`, `extension/feed`, `extension/fraud`,
  `extension/marketplace`, `extension/module`, `extension/payment`,
  `extension/report`, `extension/shipping`, `extension/theme`, `extension/total`
- Dinamik `extension/<extension>/<type>/<code>.*` route'ları

`extension/language`, `extension/captcha`, `extension/currency`,
`extension/dashboard`, `extension/other` ve `design/translation` ortak veya
dinamik kullanımları nedeniyle doğrudan kaldırılmamalıdır.

## Catalog storefront rotaları

API-only hedefte kaldırılacak HTML/storefront route grupları:

- `common/home`, `common/header`, `common/footer`, `common/menu`,
  `common/search`, `common/cart`, `common/currency`, `common/cookie`, layout
  position controller'ları ve `common/maintenance`
- `product/category`, `product/product`, `product/thumb`, `product/related`,
  `product/manufacturer`, `product/search`, `product/special`, `product/review`,
  `product/compare`
- `checkout/cart`, `checkout/checkout`, `checkout/confirm`, checkout address,
  payment, shipping, register, success ve failure route'ları
- `account/*`: login/logout/register, address, edit, password, forgotten,
  wishlist, order, download, return, reward, transaction, subscription,
  newsletter, affiliate ve tracking
- `information/contact`, `information/information`, `information/sitemap`,
  `information/gdpr`
- `cms/blog`, `cms/comment`
- Storefront tarafından çağrılan `extension/opencart/module/*`,
  `extension/opencart/checkout/*`, payment ve captcha route'ları

`common/pagination`, `error/*` ve `tool/upload` özel API ihtimali nedeniyle
storefront dosyalarıyla birlikte otomatik silinmemelidir.

## Catalog API rotaları

Mevcut route'ların tamamı stok e-ticaret API'sidir:

- `api/affiliate`
- `api/cart`
- `api/customer`
- `api/order`
- `api/payment_address`
- `api/payment_method`
- `api/shipping_address`
- `api/shipping_method`
- `api/subscription`
- `extension/opencart/api/coupon`
- `extension/opencart/api/reward`

Tarihsel audit sırasında `api/system/ping` route'u henüz mevcut değildi ve bu
analiz görevinde eklenmemişti. Güncel sözleşme için belgenin başındaki app/api
bölümüne bakın.

## Startup rotaları

### Admin pre-action zinciri

`system/config/admin.php` sırasıyla şunları çağırır:

1. `startup/setting`
2. `startup/session`
3. `startup/language`
4. `startup/application`
5. `startup/extension`
6. `startup/startup`
7. `startup/error`
8. `startup/event`
9. `startup/sass`
10. `startup/login`
11. `startup/authorize`
12. `startup/permission`

Bu zincir admin çekirdeğidir. `sass` storefront dışı admin tema derlemesinde de
kullanıldığı için korunmalıdır.

### Catalog pre-action zinciri

`system/config/catalog.php` sırasıyla şunları çağırır:

1. `startup/setting`
2. `startup/seo_url`
3. `startup/session`
4. `startup/language`
5. `startup/customer`
6. `startup/currency`
7. `startup/tax`
8. `startup/application`
9. `startup/extension`
10. `startup/startup`
11. `startup/marketing`
12. `startup/error`
13. `startup/event`
14. `startup/sass`
15. `startup/api`
16. `startup/maintenance`
17. `startup/authorize`

API-only minimum zincir için `setting`, `session`, `language`, `application`,
`extension`, `startup`, `error`, `event`, `api` ve `authorize` korunmalıdır.
`seo_url`, `customer`, `currency`, `tax`, `marketing`, `sass` ve `maintenance`
storefront bağımlılıkları ayrıştırıldıktan sonra kaldırma adayıdır.

`startup/startup` ayrıca `startup` tablosundaki `catalog/*` action değerlerini
dinamik olarak controller'a yollar; kayıtlar DB sorgulanmadan bilinemez.

## Event tarafından çağrılan rotalar

Kod içi sabit event action'ları:

### Admin

- `event/modification.controller`
- `event/language.before`
- `event/language.after`
- `event/modification.model`
- `event/modification.view`
- `event/language`
- `event/modification.language`
- `startup/language.after`

### Catalog

- `event/modification.controller`
- `event/language.before`
- `event/language.after`
- `event/modification.view`
- `event/theme`
- `event/language`
- `event/modification.language`
- `startup/language.after`
- `event/translation`

`event/theme` ve `event/translation` storefront render zincirindedir.
Modification ve language event'leri loader/template altyapısına bağlı çekirdek
event'lerdir.

`startup/event` ayrıca `event` tablosundaki aktif action'ları register eder.
Kurulu DB event kayıtları bu görevde sorgulanmadığından `UNKNOWN` kabul edilir.

## Cron rotaları

- Kök `cron.php`, sabit olarak `cron/cron` dispatch eder.
- `cron/cron`, `cron` tablosundaki aktif `action` değerlerini dinamik çağırır.
- Kaynakta bulunan stok işler: `cron/currency`, `cron/gdpr`,
  `cron/subscription`.
- Admin `marketplace/cron` route'u cron kayıtlarının yönetim ekranıdır.

Üç stok iş e-ticaret kaldırma adayıdır. Dispatcher ancak özel zamanlanmış iş
mekanizması kararlaştırıldıktan ve DB kayıtları doğrulandıktan sonra
sadeleştirilmelidir.

## Silinmesi riskli veya ortak kullanılan rotalar

- `common/header`, `common/footer`, `common/column_left`, `common/pagination`:
  neredeyse tüm admin formları tarafından yüklenir.
- `common/dashboard`: dashboard extension controller'larını DB ayarlarına göre
  dinamik yükler.
- `common/filemanager`, `tool/upload`: image/upload tabloları ve ortak dosya
  altyapısıyla ilişkilidir.
- `setting/*`, `startup/*`, `event/*`, `error/*`: loader, permission, ayar ve
  dispatch zincirini taşır.
- `user/*`: admin login ve route permission sisteminin veri kaynağıdır.
- `extension/*`: route'ları dinamik oluşturur; yalnız fiziksel extension
  klasörlerini silmek artık route/DB kaydı bırakabilir.
- `localisation/language`, `country`, `currency`, `zone`, `location`: stok
  checkout ile ortak olmakla birlikte gelecekteki iş modülleri kullanabilir.
- `customer/*` ve `sale/*`: kod stoktur ancak CRM/ERP kavramlarıyla çakışır.
- `catalog/controller/startup/*`: API-only bootstrap kurulmadan toplu silinmesi
  catalog dispatch'i bozar.
- `catalog/error/*`: HTML üretimi değiştirilmelidir; route altyapısı JSON 404
  için korunur.
- `cron/cron`, DB event/startup action'ları: hedefleri statik kaynakta tam olarak
  görülemeyen dinamik dispatch noktalarıdır.

## Loader ve template bulguları

- Admin sayfaları controller üzerinden ortak layout controller'larını çağırır.
- Model ve language yolları controller route gruplarıyla yoğun biçimde
  eşleşmektedir; bir controller grubu kaldırılırken paralel model/language/view
  ağacı birlikte taranmalıdır.
- Twig dosyalarında statik `{% include %}` veya `{% extends %}` bulunmamıştır;
  bu nedenle ana risk controller tarafından view'e geçirilen URL ve layout
  değişkenleridir.
- Dinamik extension controller çağrıları dashboard, payment, fraud, report,
  shipping, total, currency ve module alanlarında kullanılmaktadır.
