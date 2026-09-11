# Admin Customer ve Sale Bagimlilik Analizi

## Kapsam ve karar kurallari

Bu belge, OpenCart admin tarafindaki stok `customer` ve `sale` alanlarini inceler. Analiz sirasinda uygulama dosyalari ve veritabani degistirilmemistir.

Siniflandirmalar:

- **REMOVE**: Stok e-ticaret arayuzu veya davranisi; bagimliliklari ayrildiktan sonra fiziksel olarak kaldirilabilir.
- **KEEP**: OpenCore API veya korunan admin calisma zamani icin gerekli.
- **SHARED**: Birden fazla alan tarafindan kullaniliyor ya da gelecekteki CRM/ERP kavramlariyla cakisiyor.
- **UNKNOWN**: Dinamik event, extension veya is karari nedeniyle sahipligi koddan kesinlestirilemiyor.

Bu siniflandirma fiziksel silme ya da tablo silme talimati degildir.

## 1. Admin customer alaninin bagimliliklari

| Dosya veya grup | Sinif | Teknik gerekce |
|---|---|---|
| `controller/customer/customer.php` | SHARED | Musteri CRUD disinda address AJAX, order baglantisi, subscription payment, history, transaction, reward ve IP islemlerini birlestirir. Sale ve marketing rotalari bu controller'in autocomplete/form/history metotlarini cagirir. |
| `controller/customer/address.php` | SHARED | Customer formu ve sale order adres secimi tarafindan kullanilan AJAX adres islemlerini saglar; country, zone ve custom field modellerine baglidir. |
| `controller/customer/customer_group.php` | REMOVE | Stok customer group yonetim UI'sidir. Modeli setting, sale, tax/localisation ve language islemleri tarafindan paylasildigi icin controller ayri kaldirilmalidir. |
| `controller/customer/custom_field.php` | REMOVE | Stok custom field yonetim UI'sidir. Custom field modeli sale ve marketing formlarinda kullanilmaya devam eder. |
| `controller/customer/customer_approval.php` | REMOVE | Storefront musteri onay kuyrugu UI'sidir. Onay modeli mail event handler'lariyla paylasildigi icin model ayni adimda silinemez. |
| `controller/customer/gdpr.php` | REMOVE | Stok storefront GDPR talep yonetim UI'sidir. GDPR modeli setting/store ve mail/GDPR akislariyla paylasilir. |
| `model/customer/customer.php` | SHARED | Sale order/return/subscription, mail, localisation country/zone, marketing affiliate ve customer controller'lari tarafindan kullanilir. Address, activity, authorize, history, reward ve transaction tablolarina erisir. |
| `model/customer/custom_field.php` | SHARED | Customer/address formlari, sale order ve marketing affiliate tarafindan kullanilir; localisation language ekleme/silme islemleri de bu modele baglidir. |
| `model/customer/customer_group.php` | SHARED | Admin settings, sale order, tax rate ve localisation language islemlerinin ortak bagimliligidir. |
| `model/customer/customer_approval.php` | SHARED | Approval controller'i ile `controller/mail/customer.php` event handler'lari arasinda ortak kullanilir. |
| `model/customer/gdpr.php` | SHARED | GDPR UI, `controller/mail/gdpr.php` ve `model/setting/store.php` tarafindan kullanilir. |
| `language/en-gb/customer/*` | REMOVE | Stok customer admin ekranlarina aittir; ilgili controller rotalari kaldirildiktan sonra kullanilmaz. |
| `view/template/customer/*` | REMOVE | Stok customer admin HTML arayuzudur. Customer ve address route bagimliliklari ayrilmadan toplu silinmemelidir. |

Dis bagimliliklar:

- `setting/setting.php` ve `setting/store.php`, `customer/customer_group` modelini kullanir.
- `localisation/language.php`, customer group ve custom field ceviri kayitlarini yonetir.
- `localisation/country.php` ve `localisation/zone.php`, customer modelini silme kontrollerinde kullanir.
- `marketing/affiliate.php`, customer autocomplete, history, transaction ve custom field islemlerine baglidir.
- `model/setting/store.php`, store silme sirasinda GDPR modelini kullanir.

## 2. Admin sale alaninin bagimliliklari

| Dosya veya grup | Sinif | Teknik gerekce |
|---|---|---|
| `controller/sale/order.php` | SHARED | Stok order UI'si olmasina ragmen customer, catalog product, localisation, setting, extension ve uzak store/API akislarini birlestirir. Extension reward/coupon ekranlari `sale/order.call` rotasini cagirir. |
| `controller/sale/returns.php` | SHARED | Customer ve product autocomplete/form rotalarina, order verisine ve return localisation modellerine baglidir. Mail return handler'i model olaylarini kullanir. |
| `controller/sale/subscription.php` | SHARED | Order, customer, product, subscription plan, payment extension ve cart store akislarini birlestirir; mail subscription handler'lari controller eventlerine baglanabilir. |
| `model/sale/order.php` | SHARED | Customer modeli, mail/GDPR, returns, subscription, setting/store, order status ve currency/language yonetimi tarafindan kullanilir. |
| `model/sale/returns.php` | SHARED | Return UI, return mail handler'i, statistics eventi ve return localisation silme kontrolleri tarafindan kullanilir. |
| `model/sale/subscription.php` | SHARED | Customer payment sekmesi, subscription UI/mail ve subscription status/store islemleri tarafindan kullanilir. |
| `language/en-gb/sale/*` | REMOVE | Stok order, return ve subscription admin arayuzu metinleridir; controller/event ayrismasindan sonra kaldirilabilir. |
| `view/template/sale/*` | REMOVE | Stok sale HTML arayuzudur; customer/product AJAX ve extension cagirilari ayrilmadan toplu silinmemelidir. |

Sale controller'larinin ortak dis model bagimliliklari:

- Customer: `customer/customer`, `customer/customer_group`, `customer/custom_field`.
- Catalog: `catalog/product`, `catalog/subscription_plan`.
- Localisation: country, zone, language, currency, order status, return action/reason/status ve subscription status.
- Setting/user/tool: extension, setting, store, user/api ve tool/upload.
- Extension: dinamik `extension/payment/*` controller ve language rotalari.

## 3. Customer ile sale arasindaki capraz bagimliliklar

| Bagimlilik | Sinif | Gerekce |
|---|---|---|
| Customer -> `sale/order` | SHARED | Customer formu musteri siparislerine baglanti uretir. |
| Customer -> `sale/subscription` modeli | SHARED | Customer payment sekmesi subscription payment kayitlarini listeler ve siler. |
| Sale -> `customer/customer` | SHARED | Order, return ve subscription ekranlari musteri, adres ve autocomplete verisi kullanir. |
| Sale -> `customer/customer_group` | SHARED | Order olusturma/duzenleme customer group kurallarini kullanir. |
| Sale -> `customer/custom_field` | SHARED | Order ve adres formlari custom field tanimlarina baglidir. |
| Sale Twig -> customer rotalari | SHARED | Autocomplete, customfield, address ve customer form linkleri JavaScript icinden cagrilir. |

Customer veya sale alanini tek basina silmek diger alanda missing model/controller hatasi olusturur.

## 4. Kalan `ocadmin/model/catalog` bagimliliklari

| Model | Sinif | Kullanan alan |
|---|---|---|
| `catalog/product.php` | SHARED | Sale order, returns ve subscription; customer group; localisation stock/tax/length/weight; design layout ve setting/store. |
| `catalog/subscription_plan.php` | SHARED | Sale subscription ve localisation language islemleri. |
| `catalog/category.php` | SHARED | Design layout, setting/store ve localisation language. |
| `catalog/manufacturer.php` | SHARED | Design layout ve setting/store. |
| `catalog/information.php` | SHARED | Design layout, settings/store ve localisation language. |
| `catalog/attribute.php`, `attribute_group.php` | SHARED | Localisation language veri cogaltma ve temizleme islemleri. |
| `catalog/download.php` | SHARED | Localisation language islemleri. |
| `catalog/filter.php`, `filter_group.php` | SHARED | Localisation language islemleri. |
| `catalog/option.php` | SHARED | Localisation language islemleri. |
| `catalog/review.php` | SHARED | Korunan product modelinin urun silme islemi tarafindan yuklenir; statistics event tetikleriyle de iliskilidir. |

Bu modeller, katalog UI kaldirilmis olsa da protected customer/sale/setting/localisation kodu ayrismadan silinemez.

## 5. Mail, event ve extension bagimliliklari

### Mail handler'lari

| Alan | Sinif | Bagimlilik |
|---|---|---|
| `controller/mail/customer.php` | SHARED | Customer approval model eventlerinden sonra customer modelini okur. |
| `controller/mail/gdpr.php` | SHARED | GDPR ve customer modellerine, ayrica sale/order verisine baglidir. |
| `controller/mail/transaction.php`, `reward.php` | SHARED | Customer transaction/reward model eventlerinden sonra customer toplamlarini okur. |
| `controller/mail/returns.php` | SHARED | Sale returns ve order modellerini kullanir. |
| `controller/mail/subscription.php` | SHARED | Sale subscription/order ve customer modellerini kullanir; subscription controller eventleriyle iliskilidir. |
| `system/library/mail*` | KEEP | Korunan ortak mail tasima altyapisidir; e-ticaret handler'larindan ayri degerlendirilmelidir. |

### Event ve extension

- **UNKNOWN**: Event action'lari DB'den dinamik yuklenir. Customer approval, GDPR, transaction, reward, returns, subscription ve statistics action kayitlari fiziksel silme oncesi DB envanterinde dogrulanmalidir.
- **SHARED**: `controller/event/statistics.php`, sale returns ve catalog review model eventleriyle iliskilidir.
- **UNKNOWN**: Payment extension rotalari `extension/{extension}/payment/{code}` olarak dinamik kurulur.
- **SHARED**: Extension reward ve coupon admin Twig/controller kodu `sale/order.call` ve sale order model toplamlarini kullanir.

## 6. Cart kutuphanesi bagimliliklari

| Kutuphane | Sinif | Teknik gerekce |
|---|---|---|
| `system/library/cart/user.php` | KEEP | Admin kimlik dogrulama ve permission altyapisidir. |
| `system/library/cart/api.php` | KEEP | Korunan API kimlik/yetki altyapisidir. |
| `system/library/cart/currency.php` | KEEP | Admin uygulama startup'i kaydeder; customer mail ve sale ekranlari para formatlar. |
| `system/library/cart/length.php`, `weight.php` | KEEP | Admin startup'i kaydeder; sale order urun agirlik islemleri weight kutuphanesini kullanir. |
| `system/library/cart/cart.php` | SHARED | Admin startup'i kaydeder; sale order/subscription uzak store akislarinda `$store->cart->clear()` cagrilir. |
| `system/library/cart/customer.php` | SHARED | Admin startup'i tarafindan kaydedilir; customer/sale kaldirilsa bile startup ayrismadan silinemez. |
| `system/library/cart/tax.php` | SHARED | Admin startup'i tarafindan kaydedilir; order hesaplama ve extension akislarinin olasi ortak bagimliligidir. |
| `system/library/image.php` | KEEP | Korunan ortak image altyapisidir. |

## 7. Localisation bagimliliklari

| Model ailesi | Sinif | Kullanim |
|---|---|---|
| Country ve zone | KEEP | Customer address ve sale order adresleri; korunan ortak localisation altyapisi. |
| Language | KEEP | Customer/catalog ceviri satirlarini kopyalama/silme ve order language verisi. |
| Currency | KEEP | Sale order para birimi verisi ve currency silme kontrolleri. |
| Address format | SHARED | Customer adres ciktilari. |
| Tax rate | SHARED | Customer group bagimliligi. |
| Order status | SHARED | Sale order history ve silme kontrolleri. |
| Return action/reason/status | REMOVE | Stok return alaniyla dogrudan bagli; returns modeli/controller'i ayrildiktan sonra degerlendirilebilir. |
| Subscription status | REMOVE | Stok subscription alaniyla dogrudan bagli; subscription modeli/mail/event akisi ayrildiktan sonra degerlendirilebilir. |

## 8. Veritabani tablo aileleri

Bu tablolar icin SQL calistirilmamistir. CRM/ERP veri sahipligi belirlenmeden tablo silinmemelidir.

| Tablo ailesi | Sinif | Tespit edilen tablolar |
|---|---|---|
| Musteri ana verisi | SHARED | `customer`, `address`, `customer_group`, `customer_group_description`, `customer_ip`, `customer_login`, `customer_history`, `customer_activity`, `customer_authorize` |
| Customer custom field | SHARED | `custom_field`, `custom_field_description`, `custom_field_value`, `custom_field_value_description`, `custom_field_customer_group` |
| Finansal/loyalty musteri verisi | UNKNOWN | `customer_transaction`, `customer_reward`, `customer_wishlist`, `customer_affiliate` |
| Customer approval/GDPR | REMOVE | `customer_approval`, `gdpr`; yasal saklama ve audit gereksinimi ayrica incelenmelidir. |
| Siparis | SHARED | `order`, `order_product`, `order_option`, `order_total`, `order_history`, `order_status`, `order_subscription` |
| Return | REMOVE | `return`, `return_history`, `return_status`; ERP iade kapsami netlesmeden fiziksel silinmemelidir. |
| Subscription | REMOVE | `subscription`, `subscription_product`, `subscription_option`, `subscription_history`, `subscription_log`, `subscription_status`; tekrar eden faturalama karari beklenmelidir. |

## 9. Guvenle kaldirilabilecek dosyalar

Ilk asamada model ve tablo katmanina dokunmadan su stok UI gruplari kaldirilabilir:

1. `controller/customer/customer_group.php`, es language ve `customer_group*.twig` dosyalari.
2. `controller/customer/custom_field.php`, es language ve `custom_field*.twig` dosyalari.
3. `controller/customer/customer_approval.php`, es language ve `customer_approval*.twig` dosyalari; model ve mail handler korunarak.
4. `controller/customer/gdpr.php`, es language ve `gdpr*.twig` dosyalari; model, mail handler ve yasal veri korunarak.

Customer/customer, address ve sale UI dosyalari dis route bagimliliklari nedeniyle bu ilk gruba dahil degildir.

## 10. Once ayristirilmasi gereken SHARED veya UNKNOWN dosyalar

- `controller/customer/customer.php` ve `address.php`: sale ve marketing icin gereken AJAX endpointleri ayrilmali.
- `model/customer/customer.php`: CRM ana veri metotlari; e-ticaret reward, transaction, wishlist ve affiliate metotlarindan ayrilmali.
- `controller/sale/order.php`: extension `sale/order.call`, catalog product ve uzak store API davranislari ayrilmali.
- `controller/sale/subscription.php`: payment extension ve customer payment davranislari ayrilmali.
- Uc sale modeli: mail, localisation ve setting kontrollerinden ayrilmali.
- Mail handler ve DB event action kayitlari: tetik/action envanteri dogrulanmali.
- Dinamik payment extension kodlari ve `user/api` kullanimi: etkin extension kayitlariyla eslestirilmeli.
- `cart/cart.php`, `cart/customer.php`, `cart/tax.php`: admin startup kayitlari ve sale store akislari ayrilmali.

## 11. Onerilen fiziksel silme sirasi

1. Customer group ve custom field yonetim UI dosyalari; modeller korunur.
2. Customer approval ve GDPR yonetim UI dosyalari; mail/event/model katmani korunur.
3. DB event action ve extension kayitlarinin sadece-okunur envanteri cikarilir.
4. Sale tarafindaki extension `sale/order.call`, payment ve uzak store/cart davranislari ayrilir.
5. Sale UI controller/language/Twig dosyalari kaldirilir; paylasilan modeller gecici olarak korunur.
6. Customer autocomplete/address endpointlerinin CRM ihtiyaci belirlenir ve sale/marketing baglantilari temizlenir.
7. Customer ana UI kaldirilir; CRM icin gereken model metotlari kalir.
8. Mail/event bagimliliklari kapatildiktan sonra sale/customer modelleri tek tek yeniden siniflandirilir.
9. En son ve ayri bir migration/retention karariyla veritabani tablo aileleri degerlendirilir.

## Sonuc

Customer ve sale alanlari mevcut durumda toplu klasor silmeye uygun degildir. En dusuk riskli ilk fiziksel temizlik, customer group, custom field, customer approval ve GDPR admin UI kabuklaridir. Customer ana modeli, sale modelleri, mail/event handler'lari, localisation ve kalan catalog modelleri SHARED olarak korunmalidir.
