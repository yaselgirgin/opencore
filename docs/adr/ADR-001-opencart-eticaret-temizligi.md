# ADR-001: OpenCart E-Ticaret Katmanının Kaldırılması

- **Durum:** Kabul edildi
- **Tarih:** 2026-08-05
- **Karar sahipleri:** Proje sahibi / teknik ekip
- **Hedef kod tabanı:** OpenCart 4.x tabanlı yeni uygulama
- **İlk uygulama görevi:** E-ticaret kodlarının güvenli biçimde kaldırılması
- **Mimari yaklaşım:** Controller → Model → Database

## 1. Bağlam

Mevcut sistem, OpenCart yönetim panelini uzun yıllardır ERP ve şirket içi uygulama çatısı olarak kullanmaktadır. Yeni kod tabanında OpenCart'ın e-ticaret işlevleri kullanılmayacaktır.

Yeni uygulamada:

- `admin/` şirket içi yönetim arayüzü olacaktır.
- `catalog/` müşteri mağazası olmayacak, yalnızca özel API uçlarını barındıracaktır.
- `system/` OpenCart'ın ortak çalışma zamanı ve altyapı bileşenlerini sağlayacaktır.
- İş modülleri OpenCart'ın doğal Controller → Model → Database düzeninde geliştirilecektir.
- Ayrı bir service katmanı, özel module loader veya extension tabanlı paketleme bu kararın parçası değildir.

Bu nedenle stok OpenCart e-ticaret kodlarının projeden fiziksel olarak kaldırılmasına karar verilmiştir.

## 2. Karar

OpenCart'ın e-ticaret alanına ait stok controller, model, view, language, extension, event, startup bağımlılıkları ve ilişkili rotalar kod tabanından kaldırılacaktır.

Temizlik tek seferde körlemesine dosya silerek yapılmayacaktır. Aşağıdaki sıra izlenecektir:

1. Referans ve bağımlılık envanteri çıkarılacak.
2. Korunacak çekirdek bileşenler sabitlenecek.
3. Yönetim panelindeki e-ticaret menüleri ve erişim yolları kaldırılacak.
4. Catalog mağaza akışı kapatılıp API-only yapıya geçirilecek.
5. Kullanılmayan e-ticaret dosyaları alan alan silinecek.
6. Kalan referanslar statik arama ve çalışma testleriyle temizlenecek.
7. Veritabanı tablolarının fiziksel silinmesi ayrı ve geri alınabilir bir görevde yapılacak.

## 3. Hedef Mimari

```text
project/
├── admin/
│   ├── controller/
│   │   ├── common/
│   │   ├── startup/
│   │   ├── user/
│   │   ├── setting/
│   │   └── <özel iş modülleri>/
│   ├── model/
│   │   ├── user/
│   │   ├── setting/
│   │   └── <özel iş modülleri>/
│   ├── language/
│   └── view/template/
│       ├── common/
│       ├── error/
│       ├── user/
│       ├── setting/
│       └── <özel iş modülleri>/
├── catalog/
│   ├── controller/
│   │   ├── startup/
│   │   ├── error/
│   │   └── api/
│   ├── model/
│   │   └── <API tarafından gereken modeller>/
│   └── language/
├── system/
│   ├── engine/
│   ├── library/
│   ├── helper/
│   ├── config/
│   └── startup/
└── extension/
    └── <yalnızca gerçekten kullanılan ortak bileşenler>
```

## 4. Korunacak Bileşenler

Aşağıdaki bileşenler e-ticaret sayılmayacak ve açık bir bağımlılık analizi olmadan silinmeyecektir:

### 4.1 Sistem çekirdeği

- Registry
- Loader
- Router
- Action
- Controller
- Model
- Proxy
- Event
- Config
- Request
- Response
- Session
- Cache
- Log
- Database sürücüleri
- Language
- Url
- Document
- Template/Twig entegrasyonu
- Encryption ve güvenlik yardımcıları
- Upload ve dosya yardımcıları
- Pagination
- Mail altyapısı, özel modüller kullanıyorsa
- Image altyapısı, özel modüller kullanıyorsa

### 4.2 Admin çekirdeği

- Admin login/logout
- Admin kullanıcıları
- Kullanıcı grupları
- Route bazlı access/modify permission sistemi
- Ortak header, footer, column ve menü iskeleti
- Hata sayfaları
- Ayarlar altyapısı
- Dil yönetimi, projede kullanılacaksa
- Event yönetimi, özel kod kullanıyorsa
- Log görüntüleme, bakım ve geliştirici araçları; gereksinime göre
- Dashboard kabuğu; içindeki e-ticaret widget'ları kaldırılacaktır

### 4.3 Catalog/API çekirdeği

- Catalog bootstrap ve startup zinciri
- Router, request, response ve session altyapısı
- JSON cevap üretmek için gereken ortak sınıflar
- API kimlik doğrulaması için seçilecek altyapı
- Hata yönetimi
- CORS ve rate-limit gibi sonradan eklenecek güvenlik noktaları için giriş katmanı

## 5. Kaldırılacak E-Ticaret Alanları

Aşağıdaki alanlar stok OpenCart'a ait oldukları doğrulandıktan ve özel kod bağımlılıkları kaldırıldıktan sonra silinebilir:

- Product
- Category
- Manufacturer
- Option
- Attribute
- Filter
- Review
- Information/CMS mağaza sayfaları
- Cart
- Checkout
- Payment
- Shipping
- Tax
- Geo zone
- Order
- Return
- Recurring/subscription
- Coupon
- Voucher
- Reward points
- Affiliate
- Marketplace
- Storefront theme ve mağaza layout'ları
- Storefront search
- Wishlist
- Compare
- Customer account
- Customer group
- Newsletter/marketing campaign
- OpenCart e-ticaret raporları
- Stok dashboard kartları
- Stok e-ticaret cron görevleri
- E-ticaret event kayıtları
- E-ticaret API controller'ları
- Kullanılmayan ödeme, kargo, toplam, fraud, feed ve analytics extension'ları

> `order`, `customer`, `product` gibi genel isimler taşıyan dosyalar yalnızca dosya adına göre silinmemelidir. ERP veya özel modüller aynı kavramları kullanıyor olabilir.

## 6. Dosya Sınıflandırma Kuralı

Codex her dosyayı aşağıdaki sınıflardan birine yerleştirmelidir:

1. **CORE:** OpenCart çalışma zamanı veya admin altyapısı; korunur.
2. **ECOMMERCE-STOCK:** Stok OpenCart e-ticaret işlevi; kaldırılır.
3. **CUSTOM-BUSINESS:** Şirkete özel ERP/CRM/ajanda/teklif/fuar vb. kod; korunur.
4. **SHARED:** Hem stok hem özel kod tarafından kullanılan ortak bileşen; doğrudan silinmez, önce bağımlılık ayrıştırılır.
5. **UNKNOWN:** Güvenle sınıflandırılamayan dosya; silinmez ve raporlanır.

Mümkünse sınıflandırma şu kanıtlarla yapılmalıdır:

- Temiz OpenCart sürümü ile dosya karşılaştırması
- Git geçmişi
- Dosya namespace ve route bilgisi
- Controller/model çağrıları
- Event kayıtları
- Dil anahtarları
- Twig include ve linkleri
- Veritabanı tablo erişimleri
- Özel modüllerden gelen referanslar

## 7. Uygulama Planı

### Aşama A — Güvenli başlangıç

- Temizlik için ayrı Git branch aç.
- Çalışan mevcut sürümü tag ile işaretle.
- Uygulama ve veritabanı yedeği alındığını doğrula.
- Temiz OpenCart referans sürümünü karşılaştırma amacıyla hazırla.
- Mevcut özel route listesini çıkar.
- Mevcut özel veritabanı tablolarını ve stok tabloları ayıran bir envanter oluştur.

Beklenen çıktı:

```text
docs/cleanup/file-inventory.md
docs/cleanup/route-inventory.md
docs/cleanup/table-inventory.md
```

### Aşama B — Admin arayüzünün temizlenmesi

- Stok e-ticaret menülerini kaldır.
- Stok dashboard widget'larını kaldır.
- Login sonrası varsayılan route'u özel dashboard veya belirlenen ana sayfaya yönlendir.
- E-ticaret route'larına ait permission seçeneklerini kullanıcı grubu ekranından kaldır.
- Kaldırılan menülere ait language anahtarlarını temizle.
- Ortak header/footer ve admin oturumu çalışmaya devam etmelidir.

Aşama sonunda admin panelinde yalnızca çekirdek yönetim alanları ve özel iş modülleri görünmelidir.

### Aşama C — Catalog tarafını API-only yapmak

- Storefront ana sayfa akışını kaldır.
- Tema, layout, product, category, cart, checkout ve account rotalarını devre dışı bırak.
- Catalog kök isteği için açık bir davranış seç:

```json
{
  "status": "ok",
  "application": "OpenCore API"
}
```

veya HTTP `404/410`.

- İlk sağlık kontrolü endpoint'i ekle:

```text
GET index.php?route=api/system/ping
```

Örnek cevap:

```json
{
  "success": true
}
```

- Catalog tarafında HTML mağaza çıktısı üretilmemelidir.
- Bilinmeyen API rotaları JSON hata cevabı döndürmelidir.

### Aşama D — Kodların alan bazlı kaldırılması

Her alan ayrı commit ile kaldırılmalıdır. Önerilen sıra:

1. Marketing, affiliate, voucher, reward ve coupon
2. Review, compare, wishlist ve storefront search
3. Cart ve checkout
4. Payment, shipping, total ve fraud extension'ları
5. Tax ve geo zone
6. Product, category, manufacturer, option, attribute ve filter
7. Customer storefront/account
8. Order ve return
9. Theme, layout ve storefront CMS
10. E-ticaret raporları, dashboard kartları, event'ler ve cron'lar

Her alan kaldırıldıktan sonra:

- Kod tabanında route araması yap.
- `load->model`, `load->controller`, `load->language` çağrılarını tara.
- Twig `include`, `extends`, route linklerini tara.
- Event action referanslarını tara.
- Kullanılmayan language ve template dosyalarını temizle.
- Admin login, permission ve özel modül smoke testlerini çalıştır.
- Catalog API ping testini çalıştır.

### Aşama E — Artık referansların temizlenmesi

Aşağıdaki türde artık referans kalmamalıdır:

- Silinmiş controller route'u
- Silinmiş model yükleme çağrısı
- Silinmiş language dosyası
- Silinmiş Twig template/include
- Silinmiş event action
- Silinmiş extension kaydı
- Silinmiş dashboard widget'ı
- Silinmiş cron görevi
- Silinmiş catalog linki
- Silinmiş stok tabloya yapılan gereksiz sorgu

## 8. Veritabanı Politikası

Bu ADR'nin ilk uygulama görevinde e-ticaret tabloları fiziksel olarak silinmeyecektir.

Nedenleri:

- Özel ERP modüllerinin bazı stok tabloları kullanıyor olma ihtimali vardır.
- Kod temizliği tamamlanmadan tablo silmek geri dönüşü zor veri kaybına neden olabilir.
- Kullanılmayan tablo ile aktif bağımlılığı olan tablo önce ayrıştırılmalıdır.

İlk görev sonunda tablolar üç sınıfa ayrılacaktır:

- **KEEP:** Çekirdek veya özel modül kullanıyor.
- **MIGRATE:** Özel modül kullanıyor fakat stok e-ticaret tablosundan ayrılmalı.
- **DROP-CANDIDATE:** Hiçbir aktif kod kullanmıyor.

Tablo silme işlemi ayrı bir ADR ve ayrı bir yedekli deployment görevi olacaktır.

## 9. Mimari Sınırlar

Bu görev sırasında aşağıdakiler yapılmayacaktır:

- Service katmanı eklenmeyecek.
- Repository katmanı eklenmeyecek.
- `modules/` adlı yeni bir modül sistemi kurulmayacak.
- OpenCart loader ve router gereksiz yere yeniden yazılmayacak.
- Özel modüller extension formatına taşınmayacak.
- Controller → Model → Database akışı değiştirilmeyecek.
- Çalışan özel ERP iş kuralları yeniden tasarlanmayacak.
- Veritabanı şeması topluca yeniden adlandırılmayacak.
- Aynı commit içinde hem büyük refactor hem büyük silme yapılmayacak.

## 10. Kodlama Kuralları

- Özel admin route'ları mevcut OpenCart biçiminde kalacaktır:

```text
admin/controller/agenda/calendar.php
admin/model/agenda/calendar.php
admin/view/template/agenda/calendar.twig
```

- Örnek route:

```text
agenda/calendar
```

- API route'ları:

```text
catalog/controller/api/agenda/calendar.php
```

- Örnek API route:

```text
api/agenda/calendar
```

- Controller yalnızca istek, doğrulama, permission, model çağrısı ve response akışını yönetmelidir.
- SQL sorguları model içinde bulunmalıdır.
- Admin controller içinde doğrudan SQL yazılmamalıdır.
- Stok dosya adlarıyla çakışabilecek özel iş alanlarında açık route grupları kullanılmalıdır.
- Yeni kodda e-ticaret terminolojisi yalnızca gerçekten iş alanına aitse kullanılmalıdır.

## 11. Test Stratejisi

En az aşağıdaki smoke testler otomatik veya tekrar edilebilir şekilde hazırlanmalıdır:

### Admin

- Login sayfası açılıyor.
- Doğru kullanıcı giriş yapabiliyor.
- Hatalı kullanıcı reddediliyor.
- Logout çalışıyor.
- User ve user group ekranları açılıyor.
- Access/modify permission kontrolü çalışıyor.
- Özel dashboard açılıyor.
- En az bir özel modül list, form, save ve delete işlemi çalışıyor.
- Admin ortak header/footer kaynakları hatasız yükleniyor.
- Loglarda `Class not found`, `Could not load`, `Undefined route` hatası oluşmuyor.

### API

- Ping endpoint'i HTTP 200 ve JSON döndürüyor.
- Bilinmeyen endpoint JSON 404 döndürüyor.
- Storefront product/category/cart/checkout/account rotaları çalışmıyor.
- HTML mağaza ana sayfası açılmıyor.
- Gerekli API authentication akışı bozulmuyor.

### Statik kontroller

- Silinen route'lara aktif referans yok.
- Silinen model yollarına aktif referans yok.
- Silinen language/template dosyalarına aktif referans yok.
- E-ticaret menüleri görünmüyor.
- Stok e-ticaret event ve cron kayıtları yok.
- PHP syntax kontrolü başarılı.
- Kullanılan test/lint araçları başarılı.

## 12. Kabul Kriterleri

Görev aşağıdaki şartların tamamı sağlandığında tamamlanmış sayılır:

1. Admin paneli e-ticaret menüleri olmadan açılır.
2. Admin login, kullanıcı ve yetki sistemi çalışır.
3. Özel iş modülleri çalışmaya devam eder.
4. Catalog yalnızca API görevi görür.
5. Product, category, cart, checkout, storefront account ve benzeri mağaza rotaları erişilemez.
6. Stok e-ticaret controller/model/view/language dosyaları, güvenli olarak sınıflandırılan alanlarda fiziksel olarak kaldırılmıştır.
7. Silinen dosyalara aktif kod referansı kalmamıştır.
8. Çalışma zamanı loglarında eksik dosya, route, language veya template hatası yoktur.
9. Her kaldırma alanı bağımsız ve geri alınabilir commit'lerle kaydedilmiştir.
10. Silinmeyen şüpheli dosyalar `UNKNOWN` veya `SHARED` olarak raporlanmıştır.
11. Veritabanı tabloları bu görevde kaybedilmemiştir.
12. Temizlik raporu oluşturulmuştur.

Beklenen son rapor:

```text
docs/cleanup/cleanup-report.md
```

Raporda şunlar bulunmalıdır:

- Silinen dosya ve klasörler
- Değiştirilen startup/event/route noktaları
- Korunan çekirdekler
- `SHARED` ve `UNKNOWN` dosyalar
- Kalan e-ticaret referansları
- DROP-CANDIDATE tablolar
- Çalıştırılan testler ve sonuçları
- Bilinen riskler

## 13. Commit Stratejisi

Örnek commit dizisi:

```text
chore(cleanup): add ecommerce dependency inventories
refactor(admin): remove ecommerce navigation and dashboard widgets
refactor(catalog): convert storefront entrypoint to api-only
chore(cleanup): remove marketing and affiliate features
chore(cleanup): remove cart and checkout features
chore(cleanup): remove payment shipping and order-total extensions
chore(cleanup): remove product catalog features
chore(cleanup): remove customer storefront features
chore(cleanup): remove order and return ecommerce features
chore(cleanup): remove storefront themes layouts and reports
fix(cleanup): remove orphan routes events languages and templates
test(cleanup): add admin and api smoke tests
docs(cleanup): add final cleanup report
```

Her commit tek başına incelenebilir ve mümkün olduğunca geri alınabilir olmalıdır.

## 14. Riskler ve Önlemler

### Risk: Özel modül stok e-ticaret modelini kullanıyor

Önlem: Dosya silinmeden önce tam referans taraması yapılır. Bağımlı dosya `SHARED` olarak işaretlenir ve ayrıştırılmadan silinmez.

### Risk: Admin menü temizliği permission veya route üretimini bozar

Önlem: Menü, permission ve controller route'ları ayrı ayrı test edilir. Menüde görünmemesi erişimin kaldırıldığı varsayımı olarak kullanılmaz.

### Risk: Catalog startup zinciri mağaza bileşenlerine bağlıdır

Önlem: Startup dosyaları tek tek incelenir. API için gereken minimum zincir korunur; cart, customer veya currency gibi storefront startup'ları yalnızca bağımlılık yoksa kaldırılır.

### Risk: Extension/event kayıtları silinmiş controller çağırır

Önlem: Kod taramasına ek olarak veritabanındaki event/extension kayıtları envantere alınır. Kod silindikten sonra artık kayıtlar devre dışı bırakılır veya temizlenir.

### Risk: Büyük temizlik hata kaynağını belirsizleştirir

Önlem: Alan bazlı küçük commit, smoke test ve log kontrolü zorunludur.

## 15. Sonuçlar

### Olumlu sonuçlar

- Kod tabanı e-ticaret merkezli olmaktan çıkar.
- Admin panel yalnızca şirket işlevlerine odaklanır.
- Catalog net biçimde API katmanına dönüşür.
- Kullanılmayan route, template ve extension yükü azalır.
- Yeni modüller doğal OpenCart MVC düzeninde geliştirilebilir.
- Teknik borç ve yanlışlıkla e-ticaret bağımlılığı oluşturma riski azalır.

### Olumsuz sonuçlar

- Standart OpenCart güncellemeleri doğrudan uygulanamaz.
- OpenCart upstream değişiklikleri seçilerek taşınmak zorundadır.
- Silinen stok işlevleri gerektiğinde yeniden eklenemez; Git geçmişinden alınması gerekir.
- İlk temizlik sırasında ayrıntılı bağımlılık analizi gerekir.

Bu sonuçlar bilinçli olarak kabul edilmiştir. Proje artık standart bir OpenCart mağazası değil, OpenCart çekirdeğini kullanan özel bir iş uygulamasıdır.

---

# Codex Uygulama Talimatı

Aşağıdaki metin doğrudan Codex görevi olarak kullanılabilir:

```text
ADR-001'i uygula.

Amaç:
OpenCart kod tabanını e-ticaret işlevlerinden fiziksel olarak arındırmak; admin kullanıcı, user group, permission, settings, routing, loader, language, session, database ve template altyapısını korumak; catalog tarafını API-only hale getirmek.

Mimari sınır:
Controller → Model → Database düzenini koru. Service, repository, özel module loader veya extension mimarisi ekleme.

Çalışma yöntemi:
1. Önce repository'yi analiz et ve hiçbir dosya silmeden şu envanterleri oluştur:
   - docs/cleanup/file-inventory.md
   - docs/cleanup/route-inventory.md
   - docs/cleanup/table-inventory.md
2. Dosyaları CORE, ECOMMERCE-STOCK, CUSTOM-BUSINESS, SHARED veya UNKNOWN olarak sınıflandır.
3. Özel kodun kullandığı hiçbir dosyayı yalnızca adına bakarak silme.
4. Admin e-ticaret menülerini ve dashboard widget'larını kaldır.
5. Catalog'u API-only hale getir ve api/system/ping endpoint'ini ekle.
6. E-ticaret alanlarını ADR'deki sırayla, ayrı ve küçük commit mantığıyla kaldır.
7. Her aşamada route, model, language, Twig, event ve cron referanslarını tara.
8. Admin login, user group, permission, özel modül ve API ping smoke testlerini çalıştır.
9. Veritabanı tablolarını bu görevde DROP etme. KEEP, MIGRATE ve DROP-CANDIDATE olarak raporla.
10. Belirsiz veya ortak kullanılan dosyayı silme; SHARED/UNKNOWN olarak raporla.
11. Sonunda docs/cleanup/cleanup-report.md oluştur.

Başarı koşulları:
- Admin login ve permission çalışıyor.
- Özel iş modülleri çalışıyor.
- E-ticaret menüleri yok.
- Catalog HTML mağaza sunmuyor.
- Product, category, cart, checkout ve account rotaları çalışmıyor.
- API ping JSON ve HTTP 200 döndürüyor.
- Silinmiş dosyalara aktif referans yok.
- Runtime loglarında eksik class, controller, model, language veya template hatası yok.
- Veritabanında veri kaybı yok.

Önemli:
İlk analiz sonunda büyük risk veya özel modül bağımlılığı tespit edersen, riskli dosyaları silme. Güvenli temizliği tamamla ve kalanları raporla.
```
