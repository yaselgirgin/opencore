# OpenCore UI Geliştirme Planı — Tabler Tabanlı Arayüz

## 1. Amaç

OpenCore yönetim arayüzünü modern, tutarlı, responsive, dağıtılabilir ve kullanıcı tarafından özelleştirilebilir ortak bir UI platformu olarak geliştirmek.

Kanonik frontend foundation:

**Tabler Admin Template / `@tabler/core`**

Tabler yalnız görsel referans değildir; OpenCore'un gerçek frontend foundation'ıdır.

UI geliştirmeleri mevcut OpenCore backend, route, permission, language ve runtime davranışlarını korumalıdır.

---

## 2. Temel UI Kararı

OpenCore UI foundation'ı Tabler'dır.

Materialize veya başka bir admin template yeni geliştirmelerde UI foundation olarak kullanılmaz.

Tabler demo sayfaları birebir kopyalanmaz.

OpenCore'un kendi:

- route
- controller/model davranışı
- permission sistemi
- menü verisi
- notification sistemi
- profile/account akışları
- form ve AJAX contract'ları

Tabler component ve layout yapısına adapte edilir.

Functionality yalnız UI değişikliği gerekçesiyle yeniden tasarlanmaz.

---

## 3. Kanonik View Sınırı

UI kaynakları mevcut `app/view/` yapısı içinde tutulur.

Temel alanlar:

```text
app/view/
├── image/
├── javascript/
├── stylesheet/
└── template/
```

Tabler için paralel yeni bir view root'u oluşturulmaz.

Örneğin aşağıdaki gibi yapılar oluşturulmaz:

```text
app/view/tabler/
app/view/theme/
app/view/assets/
```

Gerekli dosyalar mevcut kategorilerin altında tutulur:

- JavaScript → `app/view/javascript/`
- CSS / SCSS / fonts → `app/view/stylesheet/`
- Images → `app/view/image/`
- Twig/layout → `app/view/template/`

---

## 4. Güncel Runtime Sözleşmesi

Kanonik frontend foundation sürümü:

**Tabler Core 1.5.0**

Runtime'da kullanılan upstream Tabler dosyaları:

```text
app/view/stylesheet/tabler/tabler.min.css
app/view/javascript/tabler/js/tabler.min.js
```

Bu dosyalar upstream vendor çıktısı olarak korunur.

OpenCore'a özel görsel uyarlamalar doğrudan vendor dosyalarına yazılmaz.

OpenCore-specific CSS:

```text
app/view/stylesheet/stylesheet.css
```

içinde tutulur.

---

## 5. Bootstrap ve JavaScript Sınırı

Tabler Bootstrap tabanlıdır.

Runtime'da birbirini tekrar eden veya çakışan iki ayrı Bootstrap CSS/JS katmanı oluşturulmamalıdır.

Mevcut OpenCore JavaScript davranışları korunmalıdır.

Kritik ortak dosya:

```text
app/view/javascript/common.js
```

`common.js`:

- Tabler JS ile overwrite edilmez,
- gereksiz yere yeniden yazılmaz,
- mevcut OpenCore frontend contract'larını korur.

Özellikle:

- file manager
- upload
- AJAX forms
- notifications
- session/auth ile ilişkili frontend davranışları

UI değişikliği sırasında bozulmamalıdır.

---

## 6. Korunacak OpenCore Yapıları

Tabler entegrasyonu nedeniyle aşağıdakiler yeniden tasarlanmamalıdır:

- Controller / Model mimarisi
- route sistemi
- Twig view mimarisi
- language sistemi
- `user` / `user_group`
- permission sistemi
- notification altyapısı
- form/controller contract'ları
- file manager
- upload
- session/auth
- database upgrade sistemi
- `app/` / `api/` uygulama sınırları
- mevcut `Opencart\...` namespace yaklaşımı

Tabler frontend foundation'dır; application architecture değildir.

---

## 7. Ana Layout

Ana hedef Tabler Vertical Layout yaklaşımıdır.

Temel yapı:

```text
┌──────────── Vertical Sidebar ────────────┬──────── Navbar ────────────────┐
│ Logo / OpenCore                         │ Search / actions / user        │
│                                         ├────────────────────────────────┤
│ Permission-aware navigation             │                                │
│                                         │          PAGE CONTENT          │
│                                         │                                │
└─────────────────────────────────────────┴────────────────────────────────┘
```

Ortak shell için temel Twig alanları:

```text
app/view/template/common/header.twig
app/view/template/common/column_left.twig
app/view/template/common/footer.twig
```

Sidebar ve header davranışları mevcut permission ve route contract'larını kullanır.

---

## 8. Sidebar ve Navigation

Sidebar:

- vertical navigation
- icon + title
- active state
- nested menu
- expand/collapse
- responsive/mobile navigation
- okunabilir grup hiyerarşisi

özelliklerini desteklemelidir.

Mevcut permission-aware menü yapısı korunur.

UI amacıyla menu controller/data yapısı yeniden yazılmaz.

---

## 9. Header / Navbar

Header en az aşağıdaki mevcut akışları desteklemelidir:

- sidebar toggle
- notifications
- language
- user profile
- logout

İleride gerçek ihtiyaç oluşursa global search eklenebilir.

Profile, language, notification ve logout aksiyonları layout tercihlerinden bağımsız olarak erişilebilir kalmalıdır.

---

## 10. Ortak Component Standardı

Yeni ve mevcut OpenCore ekranları mümkün olduğunca Tabler'ın native component ve utility class'larını kullanmalıdır.

Öncelikli componentler:

- cards
- forms
- inputs
- selects
- checkboxes / radios / switches
- tables
- modals
- dropdowns
- alerts
- badges
- tabs
- buttons
- pagination
- breadcrumbs
- filters
- empty states
- tooltips
- responsive navigation
- notifications

Amaç her modül için yeni bir CSS sistemi üretmek değil, ortak Tabler/OpenCore component dilini tekrar kullanmaktır.

### Ortak Presentation Kuralları

- Standart secondary action butonlarında `btn-light` kullanılır.
- Tabler native component/utility class'ları mümkün olduğunca özel CSS'e tercih edilir.
- Ana liste kartlarında tablo içeriği `card-body` içinde tutulur.
- Ana liste pagination ve sonuç bilgisi aynı kartın `card-footer` alanında tutulur.
- Tab veya fieldset içindeki bağımsız tablolarda pagination tabloya aitse `tfoot` kullanılabilir.
- Modal içindeki pagination mevcut modal yapısına uygun olarak `modal-footer` içinde kalabilir.
- Ortak pagination componenti ekranda en fazla 5 sayfa bağlantısı gösterecek şekilde tutulur.
- Çok dilli tekrar eden input gruplarında dil alanları görsel olarak ayrılır.

Bu kurallar presentation standardıdır; backend contract'larını değiştirmez.

---

## 11. Typography

Varsayılan olarak Tabler'ın kendi font ve typography sistemi kullanılır.

Ek font framework veya gereksiz typography override oluşturulmaz.

Özel override yalnız gerçek OpenCore ihtiyacı olduğunda minimum kapsamda yapılır.

Harici Google Fonts runtime bağımlılığı eklenmez.

---

## 12. Icons

Tabler Icons ortak ikon sistemi olarak kullanılabilir.

Yeni ikon ihtiyacında öncelik mevcut Tabler Icons setidir.

Aynı runtime'da gereksiz şekilde birden fazla ikon framework taşınmamalıdır.

Mevcut legacy ikonların toplu dönüşümü ayrı ve kontrollü bir UI görevi olarak ele alınmalıdır.

---

## 13. Kullanıcı Arayüz Tercihleri

Kullanıcı bazlı UI tercihleri profil altında yönetilir:

```text
Profil
└── Arayüz Tercihleri
```

Tercihler kullanıcı bazında database'de kalıcı tutulur.

Sistem yalnız browser `localStorage` üzerine kurulmaz.

İlk hedef preference seti:

### Color Mode

- Light
- Dark
- System

### Color Scheme

Kullanılan Tabler sürümünün desteklediği palette seçenekleri.

### Font Family

- Sans-serif
- Serif
- Monospace
- Comic

### Theme Base

- Slate
- Gray
- Zinc
- Neutral
- Stone

### Corner Radius

- 0
- 0.5
- 1
- 1.5
- 2

### Menu

- Expanded
- Collapsed

### Content Width

- Compact
- Wide

Navbar davranışı ancak gerçek ihtiyaç doğrulanırsa ayrıca eklenir.

Kalıcı değişiklik kullanıcı `Kaydet` dediğinde yazılmalıdır.

Kaydetmeden çıkıldığında mevcut kalıcı tercih korunmalıdır.

---

## 14. Lisans ve Dependency Politikası

OpenCore dağıtılabilir bir üründür.

Tabler Admin Template ve Tabler Icons lisans bildirimleri distribution içinde korunmalıdır.

Yeni frontend dependency eklenmeden önce:

1. upstream kaynak,
2. kullanılan version,
3. license,
4. redistribution şartları

doğrulanmalıdır.

Tabler demo repository'sindeki dependency'ler otomatik olarak OpenCore'a alınmaz.

Özellikle charts, editors, calendars, maps, premium/pro, illustrations ve demo-only plugin'ler yalnız gerçek ihtiyaç varsa ayrıca değerlendirilir.

Yeni üçüncü taraf dependency eklenmesi `AGENTS.md` kurallarına tabidir.

---

## 15. External CDN Politikası

Production OpenCore üçüncü taraf CDN'lere zorunlu bağımlı olmaz.

Dağıtım için gerekli:

- CSS
- JavaScript
- fonts
- icons

OpenCore ile birlikte local/self-hosted olarak gelmelidir.

CDN yalnız development veya referans amacıyla kullanılabilir.

---

## 16. Aktif UI Geliştirme Alanları

Tabler foundation, ortak shell, navigation ve temel header entegrasyonu mevcut baseline olarak korunur.

Bundan sonraki UI geliştirmeleri öncelikle aşağıdaki alanlarda ilerler:

### UI-1 — Common Component Normalization

Forms, tables, cards, modals, dropdowns, alerts, tabs, buttons, pagination ve filters üzerinde ortak Tabler/OpenCore standardını yaygınlaştır.

### UI-2 — Typography ve Görsel Tutarlılık

Sidebar hierarchy, spacing, headings, form labels, tables, controls ve buttons üzerinde Tabler foundation ile tutarlılığı tamamla.

### UI-3 — Kullanıcı UI Preferences

Profil ekranında kullanıcı bazlı UI tercihlerini mevcut user yapısı içinde minimum database değişikliğiyle kalıcı hale getir.

### UI-4 — Runtime Preferences

Kaydedilen tercihleri common shell ve ilgili componentlere uygula.

### UI-5 — Final UI Polish ve Acceptance

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

üzerinde son tutarlılık kontrolü yap.

---

## 17. Test ve Acceptance

UI görevlerinde `AGENTS.md` içindeki genel test kuralları geçerlidir.

UI değişikliğinin kapsamına göre gerekli syntax/static kontroller yapılır.

Geçici test varlıkları repository içinde oluşturulmaz.

Geçici test, debug ve doğrulama çalışmaları AGENTS.md içindeki genel test ve geçici çalışma alanı kurallarına göre repository dışında yürütülür.

Repository yalnız gerçek proje kodu ve kalıcı proje dokümantasyonu içerir.

Son görsel kabul yalnız otomatik teste bırakılmaz.

Gerekli olduğunda owner tarafından browser üzerinde özellikle şu alanlar kontrol edilir:

- login
- dashboard
- sidebar
- desktop/mobile
- dropdowns
- notifications
- profile
- logout
- forms
- tables
- modals
- file manager
- light/dark
- responsive behavior

---

## 18. Durum

Bu belge OpenCore için aktif kanonik UI geliştirme planıdır.

Kanonik yaklaşım:

```text
OpenCore backend/runtime
+
Tabler Core UI foundation
+
OpenCore Twig/menu/permission behavior
+
minimum OpenCore-specific customization
```

UI geliştirmeleri mümkün olduğunca Tabler'ın ortak component sistemini kullanmalı ve mevcut OpenCore application davranışını korumalıdır.
