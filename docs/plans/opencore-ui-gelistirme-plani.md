# OpenCore UI Geliştirme Planı — Tabler Tabanlı Arayüz

## Amaç

OpenCore yönetim arayüzünü modern, tutarlı, responsive, dağıtılabilir ve kullanıcı tarafından özelleştirilebilir ortak bir UI platformuna dönüştürmek.

Yeni kanonik UI foundation olarak **Tabler Admin Template / `@tabler/core`** kullanılacaktır.

Tabler bu projede yalnız görsel referans değildir; lisans ve dependency sınırları doğrulandığı ölçüde OpenCore'un gerçek frontend foundation'ı olarak kullanılacaktır.

OpenCore backend/controller/model/route/permission mimarisi korunacaktır.

---

# Owner Kararı

Önceki Materialize referanslı UI implementation hattı terk edilmiştir.

Materialize:

- artık OpenCore UI foundation değildir,
- yeni implementation için referans değildir,
- Envato lisanslı asset veya kodları OpenCore'a alınmayacaktır.

`develop` branch, Materialize UI çalışması başlamadan önceki doğrulanmış noktaya geri alınmıştır.

Yeni UI çalışması Tabler ile temiz başlangıç yapacaktır.

---

# Tabler Seçim Gerekçesi

Tabler:

- açık kaynak bir admin/dashboard UI kitidir,
- Bootstrap tabanlıdır,
- Admin Template MIT License ile dağıtılmaktadır,
- Tabler Icons MIT License ile dağıtılmaktadır,
- ticari ve dağıtılabilir OpenCore ürünü için uygun bir lisans modeli sunmaktadır,
- vertical navigation, navbar, cards, forms, tables, modals, dropdowns ve benzeri ortak yönetim bileşenlerini bütünleşik bir tasarım diliyle sağlar.

Tabler lisans bildirimi OpenCore distribution'ında korunacaktır.

Tabler Core dışındaki üçüncü taraf dependency'ler ayrıca kendi lisansları üzerinden değerlendirilecektir.

MIT lisanslı Tabler Core kullanılıyor olması, demo/chart/editor veya başka üçüncü taraf paketlerinin otomatik olarak aynı lisans koşullarına sahip olduğu anlamına gelmez.

---

# Resmî Referanslar

Tabler repository:

```text
https://github.com/tabler/tabler
```

Tabler lisans:

```text
https://tabler.io/license
```

Tabler documentation:

```text
https://docs.tabler.io/
```

Tabler Vertical Layout görsel/işlevsel hedef:

```text
https://tabler.io/admin-template/preview?page=layout-vertical.html
```

Tabler Core package:

```text
https://www.npmjs.com/package/@tabler/core
```

---

# OpenCore Repository

```text
C:\xampp\htdocs\opencore
```

---

# Kanonik OpenCore View Sınırı

Tabler entegrasyonu mevcut OpenCore view directory yapısını bozmayacaktır.

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

Tabler için `app/view/` altında paralel yeni root yapılar oluşturulmayacaktır.

Örneğin:

```text
app/view/tabler/
app/view/theme/
app/view/assets/
```

oluşturulmayacaktır.

Gerekli runtime dosyaları mevcut kategorilerin altında konumlandırılacaktır:

- JavaScript → `app/view/javascript/`
- CSS / SCSS / fonts → `app/view/stylesheet/`
- Images → `app/view/image/`
- Twig/layout → `app/view/template/`

Yeni alt klasörler yalnız mevcut kategori altında, açık dependency gerekçesiyle oluşturulabilir.

---

# Tabler Vendor Yerleşim İlkesi

Tabler üçüncü taraf ama OpenCore tarafından dağıtılan bir frontend dependency olacaktır.

Tabler kaynakları:

- rastgele OpenCore CSS/JS içine kopyalanmamalı,
- upstream kimliği kaybolacak şekilde parçalanmamalı,
- lisans bildirimi kaldırılmamalı,
- demo dosyaları runtime'a taşınmamalıdır.

Kesin vendor yerleşimi ilk compatibility audit sonucunda kararlaştırılacaktır.

Ama yerleşim mutlaka mevcut:

```text
app/view/javascript/
app/view/stylesheet/
app/view/image/
```

sınırları içinde kalacaktır.

---

# Bootstrap Sözleşmesi

Tabler Core Bootstrap tabanlıdır ve Tabler dağıtım CSS'i Bootstrap stillerini kendi build çıktısında içerir.

Bu nedenle OpenCore runtime'ında:

```text
OpenCore bootstrap.css
+
Tabler tabler.css içindeki Bootstrap
```

şeklinde iki ayrı Bootstrap stil katmanı körlemesine aynı anda yüklenmemelidir.

Aynı şekilde duplicate Bootstrap JavaScript initialization oluşturulmamalıdır.

İlk compatibility audit özellikle şunları belirleyecektir:

- OpenCore'un mevcut Bootstrap sürümü,
- Tabler Core'un kullandığı Bootstrap sürümü,
- mevcut OpenCore Twig markup'ının Bootstrap bağımlılıkları,
- `common.js` içindeki Bootstrap API kullanımları,
- hangi Bootstrap CSS/JS katmanının kanonik runtime olacağı,
- mevcut OpenCore Bootstrap dosyalarının ne zaman ve nasıl kaldırılabileceği veya korunabileceği.

Bootstrap değişimi dependency audit olmadan yapılmayacaktır.

---

# `common.js` Koruma Sözleşmesi

Kritik dosya:

```text
C:\xampp\htdocs\opencore\app\view\javascript\common.js
```

`common.js`, OpenCore runtime contract'ının parçasıdır.

Tabler entegrasyonu sırasında:

- körlemesine replace edilmemeli,
- Tabler JS ile overwrite edilmemeli,
- mevcut OpenCore davranışları gereksiz yere yeniden yazılmamalı,
- file manager, upload, AJAX forms, notifications, session davranışları ve diğer OpenCore frontend contract'ları korunmalıdır.

Tabler ile conflict varsa önce conflict açıkça sınıflandırılmalıdır.

Tabler'a geçiş bahanesiyle application davranışı değiştirilmemelidir.

---

# Korunacak OpenCore Yapıları

Tabler entegrasyonu nedeniyle aşağıdakiler yeniden tasarlanmamalıdır:

- PHP controller/model mimarisi
- route sistemi
- Twig view mimarisi
- `user` / `user_group` yapısı
- permission sistemi
- notification altyapısı
- form/controller contract'ları
- file manager davranışı
- upload mekanizması
- session/auth davranışı
- database upgrade sistemi
- kanonik `app/` / `api/` mimarisi
- OpenCart tabanlı `Opencart\...` namespace yapısı

Tabler yalnız frontend foundation'dır.

---

# Görsel Hedef

Ana görsel/işlevsel hedef Tabler Vertical Layout olacaktır.

Hedef shell:

```text
┌──────────── Vertical Sidebar ────────────┬──────── Navbar ────────────────┐
│ Logo / OpenCore                         │ Search / actions / user        │
│                                         ├────────────────────────────────┤
│ Permission-aware navigation             │                                │
│                                         │          PAGE CONTENT          │
│                                         │                                │
│                                         │                                │
└─────────────────────────────────────────┴────────────────────────────────┘
```

Tabler demo sayfası birebir kopyalanmayacaktır.

OpenCore'un kendi:

- menü verisi,
- routes,
- permissions,
- notifications,
- profile/account actions

Tabler component markup'ına adapte edilecektir.

---

# Sidebar / Menü

OpenCore'un mevcut permission-aware menü ağacı korunacaktır.

Tabler tarafında hedef:

- vertical sidebar
- ikon + başlık
- active state
- nested menu
- expand/collapse
- responsive/mobile navigation
- kompakt/collapsed kullanım
- okunabilir grup hiyerarşisi

Menu controller/data yapısı UI uğruna yeniden yazılmamalıdır.

---

# Header / Navbar

Tabler navbar yapısı OpenCore'a adapte edilecektir.

En az:

- sidebar toggle
- notifications
- language
- user profile
- logout
- ileride global search

alanlarını destekleyecektir.

Mevcut OpenCore profile, notification, language ve logout route/behavior contract'ları korunacaktır.

---

# Ortak Component Sistemi

Yeni OpenCore modülleri mümkün olduğunca Tabler'ın ortak component dilini kullanacaktır.

Öncelikli componentler:

- Cards
- Forms
- Inputs
- Selects
- Checkboxes / radios / switches
- Tables
- Modals
- Dropdowns
- Alerts
- Badges
- Tabs
- Buttons
- Pagination
- Breadcrumbs
- Filters
- Empty states
- Tooltips
- Offcanvas / responsive navigation
- Notifications

Amaç her yeni modülde yeni CSS üretmek değil, ortak Tabler/OpenCore component sözleşmesini tekrar kullanmaktır.

---

# Tabler Icons

Tabler Icons, MIT lisanslı ortak ikon sistemi olarak değerlendirilecektir.

İlk audit:

- mevcut Font Awesome kullanımını,
- Tabler Icons ile overlap'i,
- webfont / SVG kullanım seçeneklerini,
- mevcut OpenCore ikon contract'larını

inceleyecektir.

Bir geçiş kararı verilirse ikon sistemi batch halinde değiştirilecektir.

Aynı runtime'da gereksiz çift ikon framework taşınmayacaktır.

---

# Typography

OpenCore varsayılan tipografisi için **Tabler'ın kendi varsayılan font ve typography sistemi** kullanılacaktır.

Önceki Public Sans tercihi iptal edilmiştir.

Amaç:

- Tabler'ın resmî Vertical Layout demosundaki tipografi görünümünü korumak,
- ekstra font override katmanı oluşturmamak,
- Tabler'ın spacing, heading, control ve navigation ölçeklerini mümkün olduğunca upstream davranışına yakın tutmak,
- yalnız gerçek OpenCore ihtiyacı ortaya çıkarsa minimum typography override uygulamaktır.

Harici Google Fonts runtime bağımlılığı eklenmeyecektir.


# Kullanıcı Arayüz Tercihleri

Kullanıcı bazlı UI preference sistemi, Tabler'ın kendi **Theme Settings** modelini temel alacaktır.

Ayarlar OpenCore içinde:

```text
Profil
└── Arayüz Tercihleri
```

altında yönetilecektir.

Tabler demo içindeki ayar paneli runtime'da ayrı bir floating/customizer panel olarak kullanılmayacaktır.

Tercihler kullanıcı bazında DB'de kalıcı saklanacaktır. Kullanıcı başka bir cihazdan giriş yaptığında kendi kayıtlı arayüz tercihleri uygulanmalıdır.

## Renk Modu

- Light
- Dark
- System

`System`, işletim sistemi / browser `prefers-color-scheme` tercihine uyacaktır.

## Renk Şeması

Tabler'ın desteklediği theme color palette seçenekleri kullanılacaktır.

OpenCore ayrıca kendi özel palette framework'ünü üretmeyecektir.

Kesin palette listesi kullanılan Tabler sürümünün gerçek Theme Settings seçeneklerinden alınacaktır.

## Font Family

Tabler Theme Settings modelindeki seçenekler kullanılacaktır:

- Sans-serif
- Serif
- Monospace
- Comic

OpenCore varsayılanı Tabler'ın kendi varsayılan font ailesi olacaktır.

Önceki Public Sans owner tercihi iptal edilmiştir.

## Theme Base

Tabler'ın desteklediği taban ton seçenekleri kullanılacaktır:

- Slate
- Gray
- Zinc
- Neutral
- Stone

## Corner Radius

Tabler'ın Theme Settings modelindeki radius seçenekleri kullanılacaktır:

- 0
- 0.5
- 1
- 1.5
- 2

## OpenCore Yerleşim Tercihleri

Tabler Theme Settings dışında OpenCore'un gerçek ihtiyacı olan layout tercihleri ayrıca tutulabilir.

İlk hedef:

### Sol Menü

- Expanded
- Collapsed

### İçerik Genişliği

- Compact
- Wide

### Navbar

Yalnız Tabler runtime ve OpenCore kullanımında gerçek ihtiyaç doğrulanırsa:

- Sticky
- Static
- Auto-hide

gibi seçenekler değerlendirilebilir.

Kritik profile, language, notification ve logout aksiyonları hiçbir layout tercihinde erişilemez hale gelmemelidir.

## Uygulama İlkesi

Tabler'da doğal karşılığı olmayan eski Materialize kavramları taşınmayacaktır.

Özellikle aşağıdakiler sırf önceki UI denemesinde kullanılmış oldukları için korunmayacaktır:

- Semi Dark
- Materialize Skin / Bordered kavramı
- Materialize customizer davranışları

Yeni preference modeli Tabler'ın gerçek capabilities setine dayanacaktır.

## Preview / Save Davranışı

Profil ekranında ayarlar değiştirilirken mümkünse anlık preview uygulanabilir.

Ancak:

- kalıcı değişiklik yalnız kullanıcı `Kaydet` dediğinde DB'ye yazılmalı,
- kaydetmeden çıkılırsa eski kalıcı tercihler korunmalıdır,
- preference sistemi yalnız browser `localStorage` üzerine kurulmamalıdır.

Kesin DB schema ve runtime uygulaması UI-T7 / UI-T8 sırasında mevcut user yapısı incelenerek minimum kapsamla tasarlanacaktır.


# Lisans ve Dağıtım

OpenCore dağıtılabilir bir üründür.

Tabler Admin Template MIT License şartları gereği ilgili copyright ve izin bildirimi OpenCore distribution'ında korunacaktır.

Aynı kural Tabler Icons için de geçerlidir.

Her yeni frontend dependency için:

1. upstream kaynak,
2. kullanılan version,
3. license,
4. redistribution şartları

kayda alınmalıdır.

Tabler demo repository'sindeki her dependency otomatik olarak OpenCore'a alınmayacaktır.

Özellikle:

- charts
- editors
- calendars
- maps
- premium/pro
- illustrations
- demo-only plugins

gerektiğinde ayrıca incelenecektir.

İhtiyaç olmayan dependency runtime'a eklenmemelidir.

---

# External CDN Politikası

Production OpenCore üçüncü taraf CDN'lere zorunlu bağımlı olmayacaktır.

Dağıtılan ve gerekli:

- CSS
- JS
- fonts
- icons

OpenCore ile birlikte local/self-hosted gelmelidir.

CDN yalnız development/demo referansı olabilir.

---

# Yeni Geliştirme Fazları

## UI-T0 — Tabler / OpenCore Compatibility Audit

### Amaç

Implementation öncesi mevcut OpenCore frontend ile Tabler Core arasındaki kesin teknik sınırı çıkarmak.

### İncelenecek

- mevcut Bootstrap version ve kullanım alanları
- jQuery bağımlılıkları
- `common.js`
- header / column_left / footer
- Bootstrap JS API kullanımları
- OpenCore form/modal/dropdown markup
- Tabler Core CSS/JS dependency zinciri
- Tabler Core Bootstrap entegrasyonu
- Tabler Icons
- required vs demo-only assets
- license inventory
- distribution strategy

### Çıktı

UI-T1 için kesin minimum dosya ve migration kapsamı.

### Kural

Read-only.

Kod veya DB değiştirilmez.

---

## UI-T1 — Tabler Core Foundation

### Amaç

Tabler Core'un yalnız gerekli runtime foundation'ını OpenCore'a eklemek.

### Kapsam

- MIT license/attribution
- gerekli Tabler core CSS/JS
- gerekli local fonts/icons kararı
- Bootstrap duplicate katmanının kontrollü çözümü
- mevcut `app/view/` yapısına doğru yerleşim

### Kapsam Dışı

- full shell dönüşümü
- menu redesign
- preferences
- dashboard redesign
- business module UI

---

## UI-T2 — Vertical Shell

### Amaç

Tabler Vertical Layout temeliyle OpenCore common shell oluşturmak.

Ana alanlar:

```text
app/view/template/common/header.twig
app/view/template/common/column_left.twig
app/view/template/common/footer.twig
```

Hedef:

- sidebar
- navbar
- page wrapper
- content
- footer
- responsive shell

OpenCore route/controller contract korunacaktır.

---

## UI-T3 — Permission-aware Navigation

### Amaç

Mevcut OpenCore menü ağacını Tabler navigation markup ve görsel diliyle sunmak.

Kapsam:

- active state
- nested items
- icons
- expanded/collapsed
- desktop/mobile behavior

Permission logic yeniden yazılmayacaktır.

---

## UI-T4 — Header Account / Notification Actions

### Amaç

Mevcut:

- profile
- language
- notifications
- logout

akışlarını Tabler navbar/dropdown componentleriyle adapte etmek.

Functionality değil presentation değişmelidir.

---

## UI-T5 — Common Component Normalization

### Amaç

OpenCore genel ekranlarını Tabler component standardına getirmek.

Öncelik:

- forms
- tables
- cards
- modals
- dropdowns
- alerts
- tabs
- buttons
- pagination
- filters

Bu aşama yeni Ajanda ve Ar-Ge modüllerinin ortak görsel temelini tamamlamalıdır.

---

## UI-T6 — Typography Alignment

### Amaç

Tabler foundation üzerinde OpenCore tipografisini resmî Tabler Vertical Layout görünümüne yakın tutmak.

Hedef:

- Tabler varsayılan font sistemi
- headings
- menu typography
- tables
- form labels
- controls
- buttons

Özel font override ancak gerçekten gerekli olduğunda minimum kapsamla uygulanacaktır.

Public Sans kullanılmayacaktır.


## UI-T7 — Kullanıcı UI Preferences

### Amaç

Profil sayfasına Tabler Theme Settings modelini temel alan kullanıcı bazlı UI tercihlerini eklemek ve kalıcı saklamak.

İlk hedef preference seti:

- Color Mode: Light / Dark / System
- Color Scheme: kullanılan Tabler sürümünün desteklediği palette
- Font Family: Sans-serif / Serif / Monospace / Comic
- Theme Base: Slate / Gray / Zinc / Neutral / Stone
- Corner Radius: 0 / 0.5 / 1 / 1.5 / 2
- Menu: Expanded / Collapsed
- Content Width: Compact / Wide
- yalnız gerçek ihtiyaç doğrulanırsa Navbar davranışı

Bu fazda preference verileri kullanıcı bazında DB'de kalıcı hale getirilecektir.

Runtime theme/layout uygulaması UI-T8 kapsamıdır.


## UI-T8 — Runtime Preferences

### Amaç

Kaydedilen tercihleri Tabler/OpenCore shell'e uygulamak.

Kapsam:

- Light / Dark / System
- Color Scheme
- Font Family
- Theme Base
- Corner Radius
- Expanded / Collapsed menu
- Content Width
- varsa onaylanmış Navbar davranışı

Tabler Theme Settings ile aynı semantic model kullanılmalıdır.

Tabler demo customizer kodu doğrudan kopyalanmayacaktır; tercihlerin OpenCore user DB kalıcılığıyla çalışan minimum runtime adaptasyonu yapılacaktır.


## UI-T9 — Final UI Polish ve Acceptance

### Amaç

Tüm ortak UI üzerinde son görsel tutarlılığı sağlamak.

Özellikle:

- sidebar spacing/hierarchy
- dropdowns
- modal headers
- form labels
- tables
- buttons
- responsive behavior
- focus states
- accessibility-visible states

kontrol edilecektir.

---

# Test ve Acceptance Yaklaşımı

UI geliştirmelerinde son acceptance yalnız LLM/otomatik teste bırakılmayacaktır.

Her anlamlı batch'te:

1. syntax/static validation
2. targeted browser smoke
3. owner manual visual acceptance

uygulanacaktır.

Özellikle manuel kontrol:

- login
- dashboard
- long sidebar
- desktop/mobile
- dropdowns
- notifications
- profile
- logout
- forms
- tables
- modals
- file manager
- dark/light
- responsive behavior

üzerinde yapılmalıdır.

---

# Paperclip / Codex Çalışma Kuralı

Kalıcı UI kararları bu plan dosyasında tutulacaktır.

Paperclip task prompt'ları planı tekrar etmeyecektir.

Task prompt yalnız:

- task amacı
- ilgili path/dosyalar
- task'a özel sınırlar
- beklenen çıktı

ile minimum tutulacaktır.

Genel proje sözleşmeleri Skills ve kanonik ADR/plan dosyalarından okunacaktır.

---

# Test Workspace Kuralı

Gerekli geçici/E2E testler yalnız:

```text
C:\xampp\htdocs\opencore_test
```

altında task'a özel klasörlerde yapılabilir.

Ana repo içinde geçici test fixture oluşturulmayacaktır.

Gerekirse task'a özel izole DB/schema kullanılacaktır.

Task bitince yalnız task'a ait test varlıkları temizlenecek, `opencore_test` ana klasörü korunacaktır.

---

# Git Kuralı

Her UI batch'i owner kabulünden sonra gerektiğinde kontrollü checkpoint commit ile sabitlenebilir.

Git işlemleri `opencore-git-owner-approval` sözleşmesine tabidir.

- otomatik push yok
- main'e otomatik geçiş yok
- owner onaysız commit/reset/merge/rebase yok

---

# İlk Sıradaki Task

Yeni Tabler hattının ilk gerçek görevi:

```text
UI-T0 — Tabler / OpenCore Compatibility Audit
```

olacaktır.

Bu task implementation yapmayacak.

Amaç Tabler Core'un OpenCore'a hangi minimum, güvenli ve dağıtılabilir şekilde entegre edileceğini kesinleştirmektir.

---

# Durum

Bu belge OpenCore için güncel kanonik UI geliştirme planıdır.

Geçersiz yaklaşım:

```text
Materialize referanslı özel UI üretimi
```

Yeni yaklaşım:

```text
OpenCore backend/runtime
+
Tabler Core UI foundation
+
OpenCore Twig/menu/permission davranışı
+
minimum OpenCore-specific customization
```

Tabler'ın ortak component sistemi mümkün olduğunca korunacak; OpenCore yalnız gerekli ürün kimliği ve davranış adaptasyonlarını ekleyecektir.
