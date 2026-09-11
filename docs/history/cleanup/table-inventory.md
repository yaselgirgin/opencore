# OpenCart Tablo Envanteri

## Kapsam ve sınıflandırma

Bu liste PHP kaynaklarındaki `DB_PREFIX` içeren SQL sorgularından çıkarılmıştır.
SQL çalıştırılmamış, veritabanı şeması veya verisi sorgulanmamıştır. Dinamik tablo
adı kullanan backup/upgrade benzeri kodlar nedeniyle çalışma veritabanında bu
listede görünmeyen tablolar bulunabilir.

- `KEEP`: Admin/API çekirdeği veya korunması kararlaştırılmış altyapı kullanıyor.
- `MIGRATE`: Özel iş kodunun kullandığı ve stok tablodan ayrılması gereken tablo.
- `DROP-CANDIDATE`: Yalnız kaldırılması hedeflenen stok e-ticaret kodu kullanıyor;
  kod temizliği, DB yedeği ve ayrı ADR sonrasında silme adayıdır.
- `UNKNOWN`: Adı şirket iş alanıyla çakışıyor, ortak kullanım ihtimali var veya
  DB tarafındaki dinamik kayıtlar sorgulanmadan karar verilemiyor.

Mevcut commit'te özel iş modülü bulunmadığı için kanıtlanmış bir `MIGRATE` tablo
yoktur. Bu durum ileride taşınacak eski ERP verisinin olmadığı anlamına gelmez.

## KEEP

| Tablolar | Gerekçe |
| --- | --- |
| `user`, `user_group`, `user_login`, `user_token`, `user_authorize` | Admin login, kullanıcı, user group ve access/modify permission altyapısı. |
| `session` | Admin ve catalog/API oturum sürücüsü. |
| `setting`, `store` | Startup ve korunacak ayarlar altyapısı; `store` tek mağaza konfigürasyonunda da okunur. |
| `language` | Admin/API yerelleştirme ve language startup. |
| `event`, `startup` | Dinamik event ve startup action kayıtları. İçerikleri ayrıca incelenmelidir. |
| `cron` | Ortak zamanlanmış iş dispatcher'ı. Stok action kayıtları daha sonra temizlenmelidir. |
| `api`, `api_history`, `api_ip`, `api_session` | Mevcut catalog API authentication altyapısı; hedef auth kararı verilene kadar korunur. |
| `extension`, `extension_install`, `extension_path`, `modification`, `module` | Loader/extension/modification altyapısının kayıtları. E-ticaret satırları ayrıca temizlenmelidir. |
| `upload` | Ortak upload/file manager altyapısı. |
| `notification`, `notification_target`, `notification_user` | Admin bildirim çekirdeği: bildirim, hedefleme ve kullanıcı okuma/dismiss durumu birlikte tutulur. |
| `translation` | Admin dil/çeviri altyapısıyla ortak kullanılır. |

## MIGRATE

Statik kaynakta `CUSTOM-BUSINESS` kod bulunmadığından bu sınıfa güvenle
yerleştirilebilen tablo yoktur. Eski ERP/CRM verisi taşınacaksa özellikle
`customer`, `order`, `product`, `category` ve ilişkili tablolar için ayrı bir
eşleme çalışması yapılmalıdır.

## DROP-CANDIDATE

Bu tablolar yalnız stok OpenCart e-ticaret modelleri, cart kütüphanesi veya stok
extension'lar tarafından kullanılmaktadır. "Aday" ifadesi koşulludur; bu görevde
silinmezler.

### Ürün ve katalog

- `attribute`, `attribute_description`, `attribute_group`,
  `attribute_group_description`
- `category`, `category_description`, `category_filter`, `category_path`,
  `category_to_layout`, `category_to_store`
- `download`, `download_description`, `download_report`
- `filter`, `filter_description`, `filter_group`, `filter_group_description`
- `identifier`
- `manufacturer`, `manufacturer_to_layout`, `manufacturer_to_store`
- `option`, `option_description`, `option_value`, `option_value_description`
- `product`, `product_attribute`, `product_bestseller`, `product_code`,
  `product_description`, `product_discount`, `product_filter`, `product_image`,
  `product_option`, `product_option_value`, `product_related`, `product_report`,
  `product_reward`, `product_subscription`, `product_to_category`,
  `product_to_download`, `product_to_layout`, `product_to_store`,
  `product_viewed`
- `review`
- `subscription_plan`, `subscription_plan_description`

### Cart, checkout, satış ve abonelik

- `cart`
- `coupon`, `coupon_category`, `coupon_history`, `coupon_product`
- `order`, `order_history`, `order_option`, `order_product`, `order_total`,
  `order_subscription`
- `subscription`, `subscription_history`, `subscription_log`,
  `subscription_option`, `subscription_product`
- `fraud_ip`

### Storefront müşteri

- `customer_activity`, `customer_affiliate`, `customer_affiliate_report`,
  `customer_approval`, `customer_authorize`, `customer_history`, `customer_ip`,
  `customer_login`, `customer_online`, `customer_reward`, `customer_search`,
  `customer_token`, `customer_transaction`, `customer_wishlist`
- `gdpr`

### İade

- `return`, `return_action`, `return_history`, `return_reason`, `return_status`

### Pazarlama, CMS ve tasarım

- `marketing`, `marketing_report`
- `antispam`
- `article`, `article_comment`, `article_description`, `article_rating`,
  `article_to_layout`, `article_to_store`
- `topic`, `topic_description`, `topic_to_layout`, `topic_to_store`
- `banner`, `banner_image`
- `information`, `information_description`, `information_to_layout`,
  `information_to_store`
- `layout`, `layout_module`, `layout_route`
- `seo_url`, `theme`

### E-ticaret raporlama

- `statistics`

## UNKNOWN

Bu tablolar mevcut kaynakta stok OpenCart tarafından kullanılır; ancak şirket
içi ERP/CRM/lojistik anlamıyla doğrudan çakıştıkları veya ortak platform verisi
olabilecekleri için gereksinim ve veri incelemesi yapılmadan drop adayı değildir.

| Tablolar | Belirsizlik nedeni |
| --- | --- |
| `customer`, `customer_group`, `customer_group_description` | Stok customer/account kullanır; gelecekteki CRM müşteri sahipliği ve API auth modeli belirlenmemiştir. |
| `address` | Stok customer/checkout kullanır; CRM müşteri adresleriyle çakışabilir. |
| `custom_field`, `custom_field_customer_group`, `custom_field_description`, `custom_field_value`, `custom_field_value_description` | Stok müşteri form alanlarıdır; özel iş formlarında yeniden kullanım ihtimali bilinmiyor. |
| `address_format`, `country`, `country_description`, `zone`, `zone_description`, `location` | Adres/ülke verisi checkout dışında şirket içi uygulamalarca da gerekebilir. |
| `currency` | Storefront fiyatlandırması kullanır; teklif, ERP ve raporlama için de gerekli olabilir. |
| `length_class`, `length_class_description`, `weight_class`, `weight_class_description` | Ürün/kargo ölçüleridir; stok ve lojistik modülleri kullanabilir. |
| `stock_status` | Storefront stok etiketi olmakla birlikte ERP stok durumu kavramıyla çakışır. |
| `order_status` | Stok order akışıdır; ERP sipariş durumları için veri eşlemesi gerekebilir. |
| `subscription_status` | Stok abonelik akışıdır; aktif veri ve entegrasyon olmadığı doğrulanmalıdır. |
| `geo_zone`, `zone_to_geo_zone`, `tax_class`, `tax_rate`, `tax_rate_to_customer_group`, `tax_rule` | Stok checkout/vergi alanıdır; şirket içi fiyat ve vergi ihtiyacı henüz belirlenmemiştir. |

## Kod bağımlılık özeti

- `system/library/cart/user.php` doğrudan `user` ve `user_group` tablolarına
  bağlıdır; bunlar dizin adına bakılarak cart ile birlikte kaldırılamaz.
- `system/library/cart/cart.php` product, option, download, tax, weight, length,
  subscription ve customer tablolarını çapraz kullanır. Cart temizliği tablo
  bağımlılıklarının önemli bölümünü serbest bırakacaktır.
- `customer`, `order` ve `product` tablo aileleri admin, catalog ve extension
  katmanlarının birden fazlası tarafından kullanılmaktadır; alan bazlı temizlik
  yapılmalıdır.
- `event`, `startup`, `cron`, `extension`, `module`, `setting` tablolarında
  controller/action yolları veri olarak tutulur. DB sorgulanmadığı için artık
  route kayıtları bu statik envanterde görülemez.
- `setting` ve `store` e-ticaret ayarları da içerir; tablo korunurken stok ayar
  satırlarının temizliği ayrı, yedekli bir veri görevi olmalıdır.

## Silme öncesi doğrulama kapısı

Her `DROP-CANDIDATE` için sonraki görevde şu koşullar birlikte sağlanmalıdır:

1. İlgili controller/model/event/cron kodu kaldırılmış olmalı.
2. `DB_PREFIX` referansı kalmadığı statik taramayla doğrulanmalı.
3. Dinamik event/startup/extension/cron kayıtları incelenmeli.
4. Eski ERP verisi ve dış entegrasyon kullanımı doğrulanmalı.
5. Yedek ve geri dönüş planı hazırlanmalı.
6. Tablo silme ayrı ADR ve açık onay kapsamında yapılmalı.
