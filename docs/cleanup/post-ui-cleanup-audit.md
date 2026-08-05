# Post-UI Cleanup Audit

## Kapsam ve yöntem

Bu belge, stok OpenCart yönetim arayüzleri ve storefront kodunun büyük ölçüde
kaldırılmasından sonraki kaynak ağacını yeniden değerlendirir. İnceleme sırasında
uygulama dosyası veya veritabanı değiştirilmemiştir. Sınıflar:

- **CORE:** Uygulama çalışma zamanı veya korunmuş admin/API çekirdeği.
- **KEEP:** Hedef sistemde açıkça korunacak işlev.
- **REMOVE:** Yalnız stok e-ticaret işlevi olduğu doğrulanan sonraki silme adayı.
- **SHARED:** Hem korunmuş akış hem stok e-ticaret kodu tarafından kullanılıyor.
- **UNKNOWN:** Statik kaynak veya DB kayıtları görülmeden güvenli karar verilemiyor.

Aramalar PHPDoc ve `docs/cleanup` eşleşmelerini aktif çağrılardan ayırmıştır.
Örneğin `marketing/affiliate` için doküman ve model PHPDoc eşleşmeleri kalırken
aktif admin route eşleşmesi yoktur. `order` ve `return` sözcükleri PHP anahtar
sözcükleriyle çakıştığından kararlar ham sözcük sayısına değil route, loader,
registry ve SQL bağlamına dayandırılmıştır.

## 1. Mevcut çekirdek yapı

| Alan | Sınıf | Bulgular |
| --- | --- | --- |
| `system/engine`, `system/framework.php`, `system/startup.php` | CORE | Registry, loader, router/action, event ve dispatch çalışma zamanı. |
| `system/config/admin.php` | CORE | Admin pre-action, hata route'u ve event zinciri. |
| `system/config/catalog.php` | CORE | API-only zincir; varsayılan `api/system`, hata `api/system.notFound`. |
| `index.php` | CORE | Catalog/API giriş noktası. |
| `cron.php` | SHARED | Catalog bootstrap sonrası sabit `cron/cron` dispatch eder; hedefler DB'den gelir. |
| Admin common/startup/error/user/setting/tool | CORE | Login, permission, ayar, ortak layout, hata ve araç altyapısı. |
| Catalog startup/API/error/tool | CORE/SHARED | API bootstrap çekirdeği korunur; legacy HTML error controller'ları ayrıca incelenmelidir. |

Catalog pre-action zinciri yalnız `setting`, `session`, `language`,
`application`, `extension`, `startup`, `error`, `event` ve `api` içerir.
Storefront customer/currency/tax/marketing/SEO startup action'ları sabit config'te
yoktur.

## 2. Kalan admin controller grupları

| Grup | Dosya sayısı | Sınıf | Karar |
| --- | ---: | --- | --- |
| `common` | 13 | CORE | Login, dashboard, layout, file manager ve developer kabuğu. |
| `startup` | 13 | CORE/SHARED | Admin bootstrap; `application` hâlâ stok cart sınıflarını register eder. |
| `user` | 4 | CORE | User, profile, API ve permission yönetimi. |
| `setting` | 2 | CORE/SHARED | Çekirdek ayarlar; stok customer/order/subscription/status verileri de yükleniyor. |
| `localisation` | 8 | KEEP/SHARED | Language, country, zone, location, currency, address/length/weight sınıfları. |
| `mail` | 9 | CORE/REMOVE/UNKNOWN | Auth mailleri gerekli; e-ticaret mailleri DB event kayıtlarına bağlı olabilir. |
| `event` | 5 | CORE/SHARED/REMOVE | Modification/language/debug çekirdek; currency ortak; statistics stoktur. |
| `extension` | 15 | SHARED/REMOVE/UNKNOWN | Dinamik extension yönetimi ile stok tip yöneticileri birlikte kalmış. |
| `marketplace` | 9 | UNKNOWN | Installer/event/startup/cron yönetir; DB ve gelecek extension politikası belirlenmeli. |
| `design` | 5 | SHARED/REMOVE | Translation ortak; banner/layout/SEO/theme storefront kalıntısıdır. |
| `customer`, `sale`, `marketing` | 0 | REMOVE tamamlandı | Dizinler boş; controller arayüzleri kaldırılmış. |

Stok tip yöneticileri `extension/analytics`, `feed`, `fraud`, `payment`, `report`,
`shipping`, `theme` ve `total` **REMOVE** adayıdır. `captcha`, `currency`,
`language`, `dashboard` **SHARED**; `module`, `marketplace`, `other` ise özel
extension politikası belirlenene kadar **UNKNOWN** kabul edilmelidir.

## 3. Kalan catalog/API controller grupları

| Grup | Sınıf | Bulgular |
| --- | --- | --- |
| `api/system.php`, `api/system/ping.php` | CORE | Kök JSON, ping ve JSON 404 davranışı. |
| `startup/*` (9 dosya) | CORE/SHARED | API bootstrap; DB extension/startup/event satırlarını dinamik yükler. |
| `event/{modification,language,debug}` | CORE | Loader ve dil event altyapısı. |
| `error/*` | UNKNOWN | Eski HTML controller referansları içerir; aktif API hata route'u bunları kullanmaz. |
| `common/pagination.php` | KEEP | Ortak pagination yardımcısı olarak korunmuş. |
| `localisation/country.php` | SHARED | API allowlist'inde değildir; country/zone JSON davranışı için gelecekte karar gerekir. |
| `tool/upload.php` | KEEP | Ortak upload API altyapısı. |
| `cron/cron.php` | SHARED | DB `cron.action` değerlerini dinamik controller olarak çağırır. |

`startup/api.php` public allowlist'i yalnız `api/system` ve `api/system/ping`
içerir; authenticated liste boştur. Bu nedenle extension altındaki coupon/reward
API controller'ları mevcut olsa da API-only router tarafından erişilemez.

## 4. Kalan modeller

### Admin model kullanıcı matrisi

| Model veya grup | Sınıf | Gerçek aktif kullanıcılar |
| --- | --- | --- |
| `customer/customer.php` | SHARED | Mail affiliate/customer/GDPR/reward/subscription/transaction; country, zone; `sale/order.php`. |
| `customer/customer_group.php` | SHARED | Setting/store; localisation language; kendi silme akışında product ve tax-rate modelleri. |
| `customer/custom_field.php` | SHARED | `localisation/language.php` dil ekleme/silme yaşam döngüsü. |
| `customer/customer_approval.php` | SHARED | `customer/customer.php`; mail event hedefleri DB'de bulunabilir. |
| `customer/gdpr.php` | SHARED | `mail/gdpr.php`, `model/setting/store.php`. |
| `marketing/affiliate.php` | SHARED | `customer/customer.php::deleteCustomer()` içinden affiliate temizliği. |
| `sale/order.php` | SHARED | Setting/store, mail subscription/returns/GDPR, localisation language/currency. |
| `sale/returns.php` | SHARED | `mail/returns.php`. |
| `sale/subscription.php` | SHARED | Setting/store ve `mail/subscription.php`. |

### Kalan `ocadmin/model/catalog` dosyaları

| Dosyalar | Sınıf | Aktif kullanıcılar |
| --- | --- | --- |
| `product.php` | SHARED | Design layout; length/weight controller; category/download/filter/review/subscription-plan/customer-group modelleri; language yaşam döngüsü. |
| `category.php` | SHARED | Design layout/model, filter, setting/store, language modeli. |
| `information.php` | SHARED | Design layout, setting/store/setting ve language modeli. |
| `manufacturer.php` | SHARED | Design layout ve setting/store. |
| `attribute.php`, `attribute_group.php`, `download.php`, `filter.php`, `filter_group.php`, `option.php`, `subscription_plan.php` | SHARED | Doğrudan UI yok; `localisation/language.php` ve katalog model zinciri kullanıyor. |
| `review.php` | SHARED | Product modelinin silme/ilişki akışları. |

Bu katalog modellerinin tamamı stok e-ticaret alanıdır; ancak dil yönetimi,
korunmuş localisation ekranları ve model içi cascade çağrıları ayrıştırılmadan
fiziksel silinmeleri güvenli değildir.

### Catalog modelleri

- **CORE:** `setting/{setting,store,event,startup,extension}`, `localisation/language`.
- **KEEP:** `tool/upload`, `user/api`.
- **SHARED:** `setting/{api,cron,module}`, localisation country/zone/currency.
- **REMOVE:** Legacy catalog localisation status/return/geo-zone modelleri ve
  `design/theme`; aktif controller loader'ı bulunmayanlar sonraki tarama adayıdır.
- **UNKNOWN:** `design/translation`, `tool/image`; gelecekteki API veya admin
  ortak kullanım kararı olmadan silinmemelidir.

## 5. Kalan mail handler'ları

| Handler | Sınıf | Gerekçe |
| --- | --- | --- |
| `authorize.php`, `forgotten.php` | CORE | Admin authorization, token ve parola kurtarma mailleri. |
| `affiliate.php`, `reward.php`, `transaction.php` | REMOVE | Stok affiliate/reward/customer transaction maili; UI kaynakları kaldırılmış. |
| `customer.php`, `gdpr.php` | REMOVE/UNKNOWN | Stok storefront customer/GDPR; DB event satırları ve model zinciri doğrulanmalı. |
| `returns.php`, `subscription.php` | REMOVE | Stok return/subscription mail akışları; sale modellerini aktif kullanıyor. |

Statik kaynakta bu mail handler'larına doğrudan `load->controller` çağrısı yoktur.
PHPDoc event trigger'ları tarihsel sözleşmeyi gösterir; gerçek aktivasyon için DB
`event` tablosundaki `action` değerleri belirleyicidir.

## 6. Kalan event handler'ları

| Handler | Sınıf | Gerekçe |
| --- | --- | --- |
| `modification.php`, `language.php`, `debug.php` | CORE | Loader/view/language event altyapısı. |
| `currency.php` | SHARED | Korunan currency engine/extension seçimi. |
| `statistics.php` | REMOVE | Return/order stok istatistikleri; `report/statistics` modeli kaldırılmış olabilir. |

Admin ve catalog `startup/event.php`, DB event satırlarını `Action` nesnesi olarak
register eder. Silinmiş controller'lara giden action'lar kaynak taramasıyla
görülemez.

## 7. Kalan extension dosyaları

`extension/opencart` altında yalnız şu fiziksel kümeler kalmıştır:

| Küme | Sınıf | Karar |
| --- | --- | --- |
| Catalog API `coupon`, `reward` controller/language | REMOVE | Public/authenticated API allowlist'inde yok; cart/customer stok bağımlılığı. |
| Captcha basic admin/catalog | SHARED | Settings ve olası API doğrulaması; gereksinim kararı gerekli. |
| Currency ECB/Fixer admin/catalog | KEEP/SHARED | Korunan currency altyapısına bağlı. |
| Admin other/cloud | UNKNOWN | Marketplace/cloud gereksinimi belirlenmedi. |
| `extension/ocmod` placeholder | CORE/UNKNOWN | OCMOD namespace path'i startup tarafından register ediliyor. |

Admin extension tip controller'ları DB `extension` tablosunu okur ve
`extension/<extension>/<type>/<code>.install|uninstall` rotalarını dinamik
çağırır. Fiziksel dosya yokluğu DB satırlarını otomatik temizlemez.

## 8. Kalan cart kütüphaneleri

| Sınıf | Registry/sınıf kullanımı | Sınıf |
| --- | --- | --- |
| `cart/cart.php` | Admin `startup/application.php` içinde `cart` olarak oluşturulur; coupon/reward extension API kullanır. | SHARED |
| `cart/customer.php` | Admin startup'ta `customer`; bazı mail/model davranışları customer verisine bağlı. | SHARED |
| `cart/tax.php` | Admin startup'ta `tax`; config country/zone ile adresler atanır. | SHARED |
| `cart/api.php` | Kaynakta aktif `new` veya registry kaydı bulunmadı. | REMOVE |
| `cart/user.php` | Admin `startup/login.php` içinde `user`; login/permission çekirdeğinin temelidir. | CORE |
| `cart/currency.php` | Admin startup'ta `currency`; mail ve localisation kullanır. | KEEP |
| `cart/length.php` | Admin startup'ta `length`. | KEEP/SHARED |
| `cart/weight.php` | Admin startup'ta `weight`. | KEEP/SHARED |

Cart, customer ve tax sınıfları stok e-ticaret kalıntısıdır; fakat admin
bootstrap'tan önce ayrıştırılmadan dosyaları silinemez.

## 9. Kalan e-ticaret localisation modelleri

| Modeller | Sınıf | Aktif kullanıcı |
| --- | --- | --- |
| `order_status`, `stock_status` | SHARED | Setting ekranı ve localisation language yaşam döngüsü. |
| `return_status` | SHARED | Setting ve language modeli. |
| `subscription_status` | SHARED | Setting, mail subscription ve language modeli. |
| `return_action`, `return_reason` | SHARED | Language ekleme/silme yaşam döngüsü. |
| `geo_zone` | SHARED | Country ve zone silme doğrulamaları. |
| `tax_rate` | SHARED | Customer-group silme cascade'i. |
| `tax_class` | REMOVE/UNKNOWN | Aktif dış loader bulunmadı; tax-rate/model ilişkisi ve DB verisi incelenmeli. |

Catalog tarafında ayrıca geo-zone, order/stock/subscription status ve return
reason modelleri kalmıştır. Mevcut API allowlist'inde bunları kullanan controller
yoktur; statik olarak **REMOVE** adayıdırlar.

## 10. Dinamik DB kayıt riskleri

Bu tablolar sorgulanmamış ve değiştirilmemiştir:

| Tablo | UNKNOWN artık kayıt riski |
| --- | --- |
| `event` | Silinmiş customer/sale/affiliate/localisation controller'ları ile mail/statistics action'ları. |
| `startup` | Silinmiş `catalog/*` action'ları; catalog startup her aktif satırı dispatch eder. |
| `cron` | Silinmiş currency/GDPR/subscription cron action'ları; dispatcher action'ı doğrudan çağırır. |
| `extension` | Kaldırılmış payment/shipping/total/fraud/feed/report/theme/module code satırları. |
| `module` | Kaldırılmış storefront/dashboard module code ve serialized ayarlar. |
| `modification` | Silinmiş route/dosyalara patch uygulayan aktif OCMOD XML kayıtları. |
| `setting` | Affiliate, cart, checkout, product, order, return, subscription, tax, payment/shipping/theme anahtarları. |

DB denetiminde action/code değerleri yalnız raporlanmalı; silme veya UPDATE ayrı
onaylı veri görevi olmalıdır.

## 11. Güvenle silinebilecek bir sonraki alanlar

1. **REMOVE:** `extension/opencart/catalog/controller/api/{coupon,reward}.php`
   ve karşılık gelen `en-gb`, `fr-fr` dil dosyaları. API allowlist dışında ve
   aktif route/controller çağrısı yoktur.
2. **REMOVE:** `system/library/cart/api.php`; aktif class/registry kullanımı yok.
3. **REMOVE:** Admin extension tip yöneticileri analytics/feed/fraud/payment/
   report/shipping/theme/total; önce DB extension kodları raporlanmalıdır.
4. **REMOVE:** Stock mail/event kümesi; önce DB event action envanteri gerekir.

## 12. SHARED veya UNKNOWN alanlar

- **SHARED:** Customer, sale, catalog ve e-ticaret localisation modelleri;
  settings, language lifecycle, mail veya model cascade çağrıları sürüyor.
- **SHARED:** Admin startup application ile cart/customer/tax registry kayıtları.
- **SHARED:** Cron dispatcher ve setting/event/startup/extension modelleri.
- **UNKNOWN:** Marketplace ve generic extension/module/other yönetimi.
- **UNKNOWN:** DB event/startup/cron/extension/module/modification/setting içeriği.
- **UNKNOWN:** Legacy catalog HTML error controller'larının kaldırılabilirliği.
- **UNKNOWN:** Captcha, OCMOD ve cloud extension gereksinimi.

## 13. Önerilen temizlik sırası

1. Ulaşılamayan coupon/reward extension API dosyalarını kaldır.
2. Aktif kullanımı olmayan `system/library/cart/api.php` dosyasını doğrulayıp kaldır.
3. DB üzerinde değişiklik yapmadan event/startup/cron/extension/module/
   modification/setting action-code envanteri çıkar.
4. Stok mail ve statistics event handler'larını, doğrulanan DB kayıtlarıyla
   birlikte ayrı görevde kaldır.
5. Setting ve localisation language içindeki e-ticaret model çağrılarını
   ayrıştır; ardından customer/sale/catalog/localisation model kümelerini kaldır.
6. Admin startup application'dan cart/customer/tax nesnelerini ayrıştır ve son
   kalan stok cart kütüphanelerini temizle.
7. Marketplace/generic extension politikasını belirleyip controller/model/DB
   katmanını son aşamada sadeleştir.

## Doğrulama sonuçları

- PHP syntax: vendor dışındaki 372 PHP dosyası, 0 hata.
- Admin login: HTTP 200.
- Dashboard, Users, User Groups, Settings: HTTP 200; kimlik doğrulaması olmadığı
  için login kabuğuna yönlendi, giriş sonrası içerik doğrulanamadı.
- Country, Zone, Location, Currency: HTTP 200; aynı login yönlendirmesi geçerli.
- API kök: HTTP 200 JSON, `OpenCore API`.
- `api/system/ping`: HTTP 200 JSON, `{"success":true}`.
- Bilinmeyen API route: HTTP 404, `application/json`.
- `C:\xampp\storage_opencore\logs\error.log`: boş.
- `git diff --check`: belge oluşturulduktan sonra ayrıca çalıştırılmalıdır.
