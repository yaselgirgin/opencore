# OpenCore UI Geliştirme Planı — Materialize Görsel/Davranış Referansı

## Amaç

OpenCore yönetim arayüzünü modern, tutarlı, responsive ve kullanıcı tarafından özelleştirilebilir ortak bir UI shell'e dönüştürmek.

Materialize HTML vertical-menu template bu çalışma için yalnızca **görsel ve davranışsal referans** olarak kullanılacaktır.

OpenCore içine Materialize'ın Envato lisansına tabi proprietary theme asset'leri, CSS dosyaları, görselleri, tasarım asset'leri veya demo/theme runtime kodları kopyalanmayacaktır.

Nihai arayüz OpenCore'un kendi frontend katmanı olacaktır.

---

## Referans Kaynaklar

OpenCore repository:

```text
C:\xampp\htdocs\opencore
```

Materialize referans kaynağı:

```text
C:\xampp\htdocs\materialize
```

Ana runtime/görsel referansı:

```text
http://localhost/materialize/html-version/full-version/html/vertical-menu-template/index.html
```

Ana filesystem referansı:

```text
C:\xampp\htdocs\materialize\html-version\full-version\html\vertical-menu-template\index.html
```

Filesystem kaynakları analiz ve karşılaştırma amacıyla incelenebilir.

Runtime davranışı değerlendirilirken `file://` yerine HTTP referansı esas alınmalıdır.

---

# Lisans Kararı

Materialize paketi tek kullanıcı / tek ürün kapsamında satın alınmış ve yeniden dağıtıma uygun bir lisans değildir.

Paket içindeki lisans açıklamasına göre:

- PHP kodu ve integrated HTML GPL kapsamındadır.
- CSS, görseller, tasarım ve diğer theme parçaları satın alınan Envato lisansına tabidir.

OpenCore dağıtılabilir bir ürün/repository olarak geliştirildiği için Materialize'ın Envato lisansına tabi theme asset'leri OpenCore distribution'ına dahil edilmeyecektir.

Bu nedenle:

## Kullanılabilir

- Materialize ekranlarını görsel referans olarak incelemek
- Layout davranışını incelemek
- Menü davranışını incelemek
- Theme seçeneklerinin kullanıcı deneyimini incelemek
- Component düzenlerini referans almak
- GPL veya bağımsız açık kaynak lisansıyla kullanılabilen genel teknik fikirleri değerlendirmek
- Gerektiğinde açık kaynak dependency'leri kendi upstream lisansları üzerinden ayrıca kullanmak

## OpenCore'a Kopyalanmayacak

- Materialize proprietary CSS/theme dosyaları
- Materialize proprietary SCSS/theme kaynakları
- Materialize görsel/tasarım asset'leri
- Demo customizer runtime'ı
- Materialize'a özgü proprietary JavaScript/theme runtime kodları
- Envato lisansına tabi font/icon/image paketleri
- Materialize distribution'ının theme/source asset'leri

Materialize kaynak dosyasının OpenCore'a fiziksel olarak kopyalanması ancak o dosyanın lisansı ayrıca açıkça doğrulanmış ve yeniden dağıtıma uygun olduğu kanıtlanmışsa değerlendirilebilir.

Tahminle asset kopyalanmayacaktır.

---

# Temel Entegrasyon İlkesi

OpenCore Materialize template uygulamasına dönüştürülmeyecektir.

Materialize:

```text
Görsel referans
+
Davranış referansı
```

olarak kullanılacaktır.

Nihai yapı:

```text
OpenCore mevcut frontend altyapısı
+
OpenCore'a ait yeni CSS / SCSS
+
OpenCore'a ait yeni JS davranışları
+
OpenCore Twig layout
```

şeklinde olacaktır.

Mevcut OpenCore runtime contract'ları korunacaktır.

---

# Korunacak OpenCore Yapıları

Materialize referansı nedeniyle aşağıdaki yapılar gereksiz yere yeniden tasarlanmamalıdır:

- PHP controller/model mimarisi
- route sistemi
- Twig view yapısı
- `user` / `user_group` permission sistemi
- notification altyapısı
- form/controller davranışları
- AJAX davranışları
- OpenCart/OpenCore frontend contract'ları
- mevcut Bootstrap kullanımı
- mevcut jQuery kullanımı
- `common.js`

---

# OpenCore View Dosya Yapısı — Kanonik Sınır

UI geliştirmesi mevcut OpenCore `app/view/` yapısını bozmayacaktır.

Kanonik temel:

```text
C:\xampp\htdocs\opencore\app\view\

├── image\
├── javascript\
│   ├── bootstrap\
│   ├── ckeditor\
│   ├── codemirror\
│   ├── jquery\
│   ├── common.js
│   └── ...
├── stylesheet\
│   ├── fonts\
│   ├── scss\
│   ├── bootstrap.css
│   ├── stylesheet.css
│   └── ...
└── template\
    ├── common\
    ├── error\
    └── ...
```

UI çalışması için `app/view/` altında paralel yeni root yapılar oluşturulmamalıdır.

Örneğin:

```text
app/view/materialize/
app/view/theme/
app/view/assets/
```

oluşturulmamalıdır.

Yeni OpenCore UI dosyaları mevcut kategoriler altında tutulacaktır:

- JavaScript → `app/view/javascript/`
- CSS / SCSS / fonts → `app/view/stylesheet/`
- Images → `app/view/image/`
- Twig/layout → `app/view/template/`

Yeni alt klasör yalnız mevcut ana kategorilerin altında ve gerçek ihtiyaç varsa oluşturulabilir.

---

# Common.js Koruma Kuralı

Kritik dosya:

```text
C:\xampp\htdocs\opencore\app\view\javascript\common.js
```

`common.js`, OpenCore runtime contract'ının parçasıdır.

UI geliştirmesi sırasında:

- replace edilmemeli
- overwrite edilmemeli
- Materialize koduyla değiştirilmemeli
- üçüncü taraf demo kodu topluca içine eklenmemeli
- mevcut fonksiyonlar gereksiz yere yeniden yazılmamalı

Yeni UI davranışları gerektiğinde mevcut OpenCore JavaScript yapısına uygun biçimde ayrı ve kontrollü olarak geliştirilecektir.

Her yeni JS davranışı `common.js` ile conflict açısından değerlendirilmelidir.

---

# Mevcut OpenCore Ortak UI Shell

İlk analizde ortak shell için aşağıdaki ana dosyalar tespit edilmiştir:

```text
app/view/template/common/header.twig
app/view/template/common/column_left.twig
app/view/template/common/footer.twig
```

Mevcut frontend yükleme zincirinde:

- Bootstrap
- jQuery
- Font Awesome
- `app/view/javascript/common.js`

davranışları bulunmaktadır.

Yeni UI shell bu mevcut yapıların üzerine adapte edilmelidir.

---

# Materialize Referansından Alınacak Görsel/Davranış Hedefleri

## Vertical Navigation

OpenCore sol menüsü Materialize vertical-menu-template davranışı ve görsel düzeni referans alınarak yeniden tasarlanacaktır.

Hedefler:

- modern vertical navigation
- ikon + başlık yapısı
- aktif menü durumu
- alt menüler
- expanded durum
- collapsed durum
- responsive/mobile davranış
- permission-aware mevcut OpenCore menü verisinin korunması

Menü verisi ve permission mantığı yeniden yazılmayacaktır.

---

## Navbar / Header

Materialize navbar/header görünümü referans alınacaktır.

Hedefler:

- modern header
- responsive davranış
- kullanıcı/profil alanı
- notification entegrasyonu
- layout ile uyumlu spacing
- navbar type tercihleri

---

## Ortak Component Görsel Dili

OpenCore'un kendi CSS/SCSS katmanında aşağıdaki component'ler standardize edilecektir:

- Cards
- Forms
- Tables
- Modals
- Alerts
- Badges
- Dropdowns
- Tabs
- Buttons
- Pagination
- Search/filter alanları

Mevcut OpenCore form ve controller davranışları korunacaktır.

---

# Template Customizer Yaklaşımı

Materialize örneğindeki sağdan açılan `Template Customizer` paneli OpenCore'a taşınmayacaktır.

Customizer yalnız hangi kullanıcı arayüz tercihlerinin faydalı olduğunu belirlemek için referans alınacaktır.

Bu tercihler OpenCore içinde:

```text
Profil
└── Arayüz Tercihleri
```

alanında yönetilecektir.

---

# Planlanan Kullanıcı Arayüz Tercihleri

## Theme

- Light
- Dark
- System

## Primary Color

Kullanıcı desteklenen ana renk seçeneklerinden birini seçebilir.

## Skin

- Default
- Bordered

## Menu

- Expanded
- Collapsed

## Navbar Type

- Sticky
- Static
- Hidden

## Content

- Compact
- Wide

## Semi Dark

- Enabled
- Disabled

Bu tercihler global setting değildir.

Her login kullanıcısı için ayrı saklanacaktır.

Tercihler yalnız browser `localStorage` içinde tutulmamalıdır.

Kullanıcı farklı bir bilgisayardan giriş yaptığında da kendi UI tercihlerini kullanabilmelidir.

Kesin DB/veri modeli mevcut OpenCore kullanıcı yapısı ve ilgili implementation task'ı sırasında minimum kapsamla belirlenecektir.

---

# Materialize Runtime Bulguları

Read-only analizde Materialize referansındaki initialization zinciri şu şekilde tespit edilmiştir:

```text
helpers.js
→ template-customizer.js
→ config.js
→ jQuery / Popper / Bootstrap / vendor dependencies
→ menu.js
→ main.js
→ page/dashboard scripts
```

Materialize `menu.js` ve `main.js`:

- Materialize'a özgü DOM yapısına
- `window.Helpers`
- `window.templateCustomizer`
- localStorage davranışına

bağlıdır.

Bu nedenle bu dosyalar OpenCore'a doğrudan taşınmayacaktır.

Materialize demo runtime'ının davranışı gerektiğinde yeniden uygulanacak ancak OpenCore'a ait kod olarak geliştirilecektir.

---

# Conflict Riski Bulunan Alanlar

Yeni UI geliştirilirken özellikle aşağıdaki alanlar korunmalı ve test edilmelidir:

- Bootstrap initialization
- jQuery/global namespace
- tooltip
- modal
- dropdown
- AJAX forms
- upload/file manager
- notification
- responsive menu
- sessionStorage
- DOMContentLoaded/load handlers
- global `window` değişkenleri
- existing OpenCore menu behavior

Materialize demo JS doğrudan eklendiğinde yüksek conflict riski bulunduğu kabul edilmiştir.

---

# UI Geliştirme Batch'leri

## UI-1 — OpenCore UI Foundation

### Amaç

Materialize vertical-menu-template'i yalnız görsel/davranışsal referans alarak OpenCore'un kendi UI foundation'ını oluşturmak.

### İlkeler

- Envato lisanslı Materialize asset'i kopyalama
- mevcut Bootstrap/jQuery altyapısını koru
- `common.js` davranışını koru
- mevcut `app/view/` klasör yapısını koru
- gerekli yeni CSS/SCSS/JS kodunu OpenCore'a ait olarak oluştur
- demo runtime veya customizer ekleme

### Kapsam

- mevcut stylesheet/SCSS yapısını inceleme
- yeni UI variables/tokens ihtiyacını belirleme
- typography
- spacing
- borders
- radius
- surfaces
- basic light theme
- navigation için gerekli temel stiller
- gelecekteki dark/theme preference yapısına uygun CSS tasarımı

### Kapsam Dışı

- tam vertical menu dönüşümü
- full navbar dönüşümü
- profil preference sistemi
- DB değişikliği
- Materialize customizer
- dashboard-specific UI
- chart/swiper/autocomplete demo scriptleri

---

## UI-2 — Common Layout Shell

### Amaç

OpenCore ortak shell'ini yeni UI foundation üzerinde yeniden düzenlemek.

Ana hedef dosyalar:

```text
app/view/template/common/header.twig
app/view/template/common/column_left.twig
app/view/template/common/footer.twig
```

Hedefler:

- header/navbar
- vertical sidebar
- content wrapper
- footer
- responsive temel shell

Materialize HTML kopyalanmayacaktır; gerekli layout OpenCore Twig yapısı içinde yeniden oluşturulacaktır.

---

## UI-3 — Permission-aware OpenCore Menu

### Amaç

Mevcut OpenCore permission-aware menü ağacını yeni vertical navigation üzerinde render etmek.

Korunacak:

- mevcut menu data
- route mantığı
- permission mantığı

Geliştirilecek:

- expanded state
- active item
- nested menu appearance
- collapsed presentation

---

## UI-4 — Responsive / Collapsed Navigation

### Amaç

- Expanded
- Collapsed
- responsive/mobile navigation

davranışlarını OpenCore'a ait JS/CSS ile tamamlamak.

Materialize `menu.js` kullanılmayacaktır.

Gerekli davranış OpenCore runtime'ına uygun biçimde yeniden geliştirilecektir.

---

## UI-5 — Profil / Arayüz Tercihleri

### Amaç

Kullanıcı profil sayfasına `Arayüz Tercihleri` bölümü eklemek.

Tercihler:

- Theme
- Primary Color
- Skin
- Menu
- Navbar Type
- Content
- Semi Dark

Kullanıcı bazında kalıcı saklama sağlanacaktır.

---

## UI-6 — Runtime Theme Preference

### Amaç

Login olan kullanıcının kayıtlı tercihlerinin ortak UI shell'e uygulanması.

Özellikle:

- Light / Dark / System
- Primary Color
- Default / Bordered
- Expanded / Collapsed
- Sticky / Static / Hidden Navbar
- Compact / Wide Content
- Semi Dark

davranışları OpenCore'un kendi theme runtime'ı olarak uygulanacaktır.

Materialize template customizer kodu kullanılmayacaktır.

---

## UI-7 — Common Component Normalization

### Amaç

Yeni modüllerin kullanacağı ortak component görsel dilini standardize etmek.

Kapsam:

- cards
- forms
- tables
- modals
- alerts
- badges
- dropdowns
- tabs
- buttons
- filters
- pagination

Bu aşamadan sonra Ajanda, Ar-Ge, CRM, ERP ve diğer yeni modüller aynı ortak UI sistemini kullanmalıdır.

---

# Kullanıcı Kabul Testi Yaklaşımı

UI çalışmaları görsel ve etkileşimsel olduğu için final acceptance yalnız otomatik testlere bırakılmayacaktır.

Her anlamlı UI batch'inden sonra:

1. deterministik syntax/static kontrolleri,
2. hedefli browser smoke kontrolleri,
3. kullanıcı tarafından manuel görsel/işlevsel acceptance

uygulanacaktır.

Özellikle:

- login
- dashboard
- sidebar
- responsive davranış
- dropdown/modal
- form davranışları
- file manager
- redirect
- notification
- theme görünümü

manuel acceptance sırasında değerlendirilebilir.

---

# Paperclip / Codex Çalışma Yaklaşımı

Kalıcı kurallar ve bu UI projesinin kararları bu plan dosyasında tutulacaktır.

Gelecek Paperclip task prompt'ları plan içeriğini tekrar etmeyecektir.

Task prompt'ları yalnız:

- task amacı
- ilgili path/dosyalar
- task'a özgü sınırlar
- beklenen çıktı

ile minimum tutulacaktır.

Her implementation batch'i küçük ve geri alınabilir olmalıdır.

Gereksiz Developer → Test → Review döngüsü oluşturulmamalıdır.

Deterministik kontroller mümkün olduğunca script veya doğrudan araçlarla yapılmalıdır.

Sol yalnız gerçek mimari veya kritik risk escalation durumlarında kullanılmalıdır.

---

# Bir Sonraki Geliştirme Adımı

Sıradaki implementation task:

```text
UI-1 — OpenCore UI Foundation
```

olacaktır.

UI-1 başlamadan önce repository'nin mevcut stylesheet/SCSS yapısı hedefli olarak incelenmeli ve yalnız gerekli minimum OpenCore UI temel katmanı oluşturulmalıdır.

Bu çalışma Materialize asset migration görevi değildir.

---

# Durum

Bu belge, önceki Materialize asset entegrasyonu yaklaşımının yerini alır.

Güncel owner kararı:

> Materialize yalnız görsel ve davranışsal referanstır. Envato lisansına tabi Materialize theme asset'leri OpenCore distribution'ına kopyalanmayacaktır.

Nihai ürün OpenCore'un kendi UI katmanı olacaktır.

Mevcut kanonik OpenCore mimarisi, ADR kararları, `app/view/` dizin yapısı ve `common.js` contract'ı korunacaktır.
