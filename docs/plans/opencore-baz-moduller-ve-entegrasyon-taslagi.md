# OpenCore Baz Modüller ve Entegrasyon Taslağı

## Amaç

OpenCore'u ERP veya CRM gibi büyük iş modüllerine geçmeden önce, şirket içi uygulamaların üzerinde çalışabileceği ortak ve entegre bir baz platform olarak ayağa kaldırmak.

Bu doküman mevcut kanonik OpenCore mimarisini değiştirmez. Buradaki amaç, bundan sonraki ürün geliştirme yönünü ve baz modüller arasındaki ilişkileri tanımlamaktır.

---

## Temel Yaklaşım

OpenCore'da üç farklı kavram birbirinden ayrı tutulacaktır:

### 1. Permission — Yetki

Mevcut `user_group` ve OpenCart/OpenCore permission mekanizması kullanılmaya devam eder.

`user_group` temel olarak kullanıcının hangi modül veya route'a erişebileceğini ve hangi işlemleri değiştirebileceğini belirler.

Örnek:

- `access` izni: Kullanıcı ilgili modülü/ekranı açabilir.
- `modify` izni: Kullanıcı ilgili modülde değişiklik yapabilir.

Kullanıcının bir ekrana gerekli erişim veya değiştirme izni yoksa, sistem bu durumu anlaşılır biçimde bildirir. Kullanıcı gerekli olduğunda sistem yöneticisinden yetki talep eder.

### 2. Membership — Ekip Üyeliği

`Team / Ekip`, `user_group` ile aynı şey değildir.

Ekipler departmanları veya yetki gruplarını temsil etmek için kullanılmaz. Bunun yerine belirli bir proje, çalışma veya amaç için farklı alanlardan kullanıcıların bir araya getirildiği çapraz fonksiyonlu çalışma gruplarıdır.

Örnek:

**Antispray Geliştirme Ekibi**

- Satıştan bir kullanıcı
- Üretimden bir kullanıcı
- Tasarımdan bir kullanıcı
- Ar-Ge'den bir kullanıcı

Aynı kullanıcı birden fazla ekipte bulunabilir.

### 3. Assignment — Atama / Sorumluluk

Görev gibi kayıtlar gerektiğinde:

- belirli bir kullanıcıya,
- bir kullanıcı grubuna,
- veya bir ekibe

atanabilir.

Bu kavram permission ve team membership'ten ayrıdır.

---

# Ana Menü Yapısı

## Ajanda

```text
/Ajanda
├── Takvim
├── Notlar
└── Görevler
```

## Ar-Ge

```text
/Ar-Ge
├── Ekipler
├── Toplantılar
├── Projeler
├── Fuarlar
└── Bilgi Bankası
```

ERP, CRM, Satış, Satın Alma ve diğer iş modülleri bu temel yapı oturduktan sonra ayrıca geliştirilecektir.

---

# Ar-Ge → Ekipler

Ekipler ortak bir temel modül olacaktır.

Bir ekip bağımsız bir kayıt olarak tutulur.

Örnek alanlar:

- Ekip adı
- Açıklama
- Durum
- Üyeler
- Oluşturan kullanıcı
- Oluşturma tarihi
- Güncelleme tarihi

Temel veri ilişkisi:

```text
Team
  ↓
Team Members
  ↓
Users
```

Önerilen mantıksal yapı:

```text
team
├── team_id
├── name
├── description
├── status
├── created_by
├── date_added
└── date_modified
```

```text
team_member
├── team_id
└── user_id
```

Kesin tablo ve alan isimleri implementation öncesinde mevcut OpenCore veri modeli incelenerek kararlaştırılacaktır.

## Ekiplerin Rolü

Ekipler özellikle aşağıdaki yapılarda kullanılacaktır:

- Projeler
- Görevler
- ileride ihtiyaç duyulan diğer ortak çalışma kayıtları

Ekipler permission sistemi değildir.

Bir kullanıcının bir modüle erişebilmesi için yine ilgili `user_group` permission'ına sahip olması gerekir.

---

# Ar-Ge → Projeler

Projeler ekiplerle bağlantılı çalışacaktır.

Örnek:

```text
Proje: S-Type Antispray Development

Ekip:
Antispray Geliştirme Ekibi

Üyeler:
- Kullanıcı A
- Kullanıcı B
- Kullanıcı C
```

Kullanıcı Projeler ekranına girdiğinde:

1. Önce `user_group` üzerinden Projeler modülüne erişim izni kontrol edilir.
2. Kullanıcının modüle erişim izni varsa, normal kullanımda kendisinin üyesi olduğu ekiplerle ilişkili projeler gösterilebilir.

Temel ilişki:

```text
Project
  ↓
Team
  ↓
Team Members
  ↓
Users
```

Proje üyeleri Team üyelerinden ayrıca kopyalanmamalıdır.

İleride bir projeye birden fazla ekip bağlanması gerekebileceği için veri modeli gereksiz biçimde tek-ekip varsayımına kilitlenmemelidir. Ancak ilk sürüm minimum kapsamla geliştirilebilir.

---

# Ajanda → Görevler

Görevler sistemin ilk ortak iş akışı modüllerinden biri olacaktır.

Bir görev:

- kullanıcıya,
- kullanıcı grubuna,
- veya ekibe

atanabilir.

Örnekler:

```text
Görev:
Yeni kalıp çizimini hazırla

Atanan:
Kullanıcı A
```

```text
Görev:
Test numunelerini hazırla

Atanan:
Antispray Geliştirme Ekibi
```

```text
Görev:
Belirli işlemi kontrol et

Atanan:
Bir kullanıcı grubu
```

Mantıksal olarak görev ataması şu üç tipi desteklemelidir:

```text
user
user_group
team
```

Kesin veri modeli implementation öncesinde tasarlanacaktır. Gereksiz karmaşık generic ACL veya relation framework kurulmayacaktır.

## Benim Görevlerim Mantığı

Kullanıcının görev listesinde aşağıdakilerden biri geçerliyse görev gösterilebilir:

- görev doğrudan kullanıcıya atanmışsa,
- görev kullanıcının `user_group` kaydına atanmışsa,
- görev kullanıcının üyesi olduğu bir ekibe atanmışsa.

---

# Görevlerin Diğer Modüllerle Entegrasyonu

Görevler yalnız bağımsız kayıtlar olmayacaktır.

Bir görev ileride ilgili bir kayıtla bağlantılı olabilir:

- Proje
- Toplantı
- Fuar
- Bilgi Bankası kaydı
- ileride CRM kaydı
- ileride ERP kaydı
- diğer modüller

Örnek:

```text
Görev:
Yeni antispray numunesini hazırla

Atanan:
Antispray Geliştirme Ekibi

İlişkili Proje:
S-Type Antispray Development

Son Tarih:
18.09.2026
```

Bu görev:

- Ajanda → Görevler içinde,
- ilgili ekibin görevlerinde,
- ilgili Projenin görevleri bölümünde,
- tarihi nedeniyle Takvim içinde

görülebilir.

Veriler modüller arasında kopyalanmamalı; gerçek kayıtlar ortak ilişkiler üzerinden kullanılmalıdır.

---

# Ajanda → Takvim

Takvim iki farklı veri tipini bir araya getirecektir.

## 1. Kullanıcının Kendi Etkinlikleri

Kullanıcı kendi özel takvim etkinliklerini oluşturabilir ve takip edebilir.

Örnek:

```text
15:30 — Kişisel Etkinlik
```

Bu kayıt kullanıcının kendi takvim verisidir.

İleride gerekirse paylaşılabilir etkinlik davranışı ayrıca değerlendirilebilir.

## 2. Sistem Kaynaklı Takvim Kayıtları

Takvim diğer modüllerde bulunan tarihli kayıtları ortak görünümde gösterebilir.

Örnek kaynaklar:

- Görev son tarihleri
- Proje tarihleri / milestone'ları
- Toplantılar
- Fuarlar
- ileride müşteri ziyaretleri
- teklif takip tarihleri
- sipariş teslim tarihleri
- diğer modüllerin tarihli kayıtları

Temel yaklaşım:

```text
TAKVİM

Kişisel
├── Kullanıcının kendi etkinlikleri

Görevler
├── Kullanıcıya atananlar
├── Kullanıcı grubuna atananlar
└── Kullanıcının ekiplerine atananlar

Projeler
├── Kullanıcının dahil olduğu projelerin tarihleri

Toplantılar
├── Kullanıcının katılımcı olduğu toplantılar

Fuarlar
└── İlgili fuar kayıtları
```

Takvim mümkün olduğunca diğer modüllerdeki kayıtların kopyasını üretmemeli; farklı kaynaklardan tarihli verileri ortak görünümde sunmalıdır.

---

# Ajanda → Notlar

Notlar ilk sürümde basit tutulabilir.

Temel kullanım:

- kullanıcının kendi notları,
- gerektiğinde paylaşılan notlar,
- ileride başka kayıtlarla ilişkilendirilebilir notlar.

İleride bir not:

- projeye,
- toplantıya,
- fuara,
- firmaya,
- CRM kaydına,
- başka bir iş kaydına

bağlanabilecek şekilde genişletilebilir.

İlk sürümde gereksiz generic paylaşım/ACL sistemi kurulmayacaktır.

---

# Ar-Ge → Toplantılar

Toplantılar menüde ilk etapta Ar-Ge altında bulunabilir ancak veri modeli yalnız Ar-Ge toplantılarına özel tasarlanmamalıdır.

İleride aynı altyapı:

- Ar-Ge toplantısı
- satış toplantısı
- müşteri toplantısı
- üretim toplantısı
- yönetim toplantısı

gibi farklı amaçlarla kullanılabilir.

Toplantılar:

- kullanıcılarla,
- ekiplerle,
- projelerle,
- görevlerle,
- takvimle

ilişkilendirilebilir.

---

# Ar-Ge → Fuarlar

Fuarlar bağımsız kayıtlar olarak yönetilebilir.

İleride:

- ekipler,
- kullanıcılar,
- görevler,
- toplantılar,
- notlar,
- dosyalar,
- CRM firmaları ve kişiler

ile ilişkilendirilebilir.

Fuar tarihleri Takvim üzerinde gösterilebilir.

---

# Ar-Ge → Bilgi Bankası

Bilgi Bankası ortak bir kurumsal bilgi havuzu olacaktır.

Basit bir dosya arşivinden daha geniş düşünülmelidir.

Örnek yapı:

```text
Bilgi Bankası
├── Kategoriler
├── Bilgi Kayıtları / Makaleler
├── Dosyalar
├── Görseller
├── Etiketler
└── İlişkili Kayıtlar
```

Örnek kullanım:

**S tipi antispray test düzeneği araştırması**

Bu kayıt altında:

- teknik notlar,
- mevzuat referansları,
- PDF dosyaları,
- görseller,
- ilgili proje,
- ilgili toplantılar

tutulabilir.

İleride Bilgi Bankası kayıtları:

- Projeler
- Fuarlar
- Firmalar
- Ürünler
- CRM kayıtları

ile ilişkilendirilebilir.

---

# Temel Mimari Ayrım

OpenCore baz modüllerinde şu üç kavram korunacaktır:

```text
PERMISSION
User Group
→ Kullanıcı hangi modül veya işlemi kullanabilir?

MEMBERSHIP
Team
→ Kullanıcı hangi çalışma/proje ekiplerinin parçasıdır?

ASSIGNMENT
User / User Group / Team
→ Bir görev veya iş kime atanmıştır?
```

Bu üç kavram birbirine dönüştürülmemeli ve birbirinin yerine kullanılmamalıdır.

---

# Yetki ve Görünürlük

Bir kullanıcının belirli bir kayıtla ilişkili olması, modül permission kontrolünü bypass etmemelidir.

Örnek:

Kullanıcı bir Project Team üyesi olsa bile Projeler modülü için gerekli `access` permission'ı yoksa modüle erişemez.

Tersi durumda, modüle erişim izni olsa bile kayıt seviyesinde Team membership gibi bir filtre uygulanıyorsa yalnız ilgili kayıtları görür.

Erişim engellendiğinde kullanıcıya anlaşılır bir mesaj gösterilmelidir. Kullanıcı gerekli yetkinin sistem yöneticisi tarafından verilmesini isteyebilir.

---

# Tasarım İlkeleri

1. Mevcut OpenCore/OpenCart kullanıcı ve permission altyapısını gereksiz yere yeniden yazma.
2. `user_group` yetkilendirme için kullanılmaya devam etsin.
3. Team ayrı ve basit bir organizasyon/çalışma ilişkisi olarak tasarlansın.
4. Gereksiz generic ACL framework oluşturma.
5. Modüller birbirlerinin verilerini kopyalamak yerine ilişki kursun.
6. Yeni modüller mevcut notification, file, cron, user ve permission altyapısını mümkün olduğunca kullansın.
7. Ortak ihtiyaç ortaya çıkmadan soyut framework geliştirme.
8. Küçük ve bağımsız geliştirme batch'leri kullan.
9. Önce temel entity ve ilişkileri kur, sonra entegrasyonları ekle.
10. ERP/CRM geliştirmesine baz platform oturduktan sonra geç.

---

# Önerilen Geliştirme Sırası

## Aşama 1 — Kullanıcı Yapısı Değerlendirmesi

Mevcut:

- `user`
- `user_group`
- permission sistemi
- session/auth ilişkileri

incelenecek.

Amaç mevcut yapıyı yeniden tasarlamak değil; Team ve sonraki modüller için hangi minimum eklemelerin gerekli olduğunu belirlemektir.

## Aşama 2 — Ar-Ge / Ekipler

İlk yeni temel entity olarak Team/Ekip altyapısı geliştirilecek.

- ekip CRUD
- ekip üyeleri
- kullanıcı ilişkileri
- permission entegrasyonu
- temel liste/form davranışı

## Aşama 3 — Ajanda / Görevler

Görevler:

- kullanıcıya,
- user group'a,
- team'e

atanabilecek.

Ekip altyapısını kullanan ilk gerçek ortak modül olacaktır.

## Aşama 4 — Ar-Ge / Projeler

Projeler ekiplerle ilişkilendirilecek.

Kullanıcıların kendilerinin dahil olduğu projeleri görebilmesi için gerekli minimum membership filtering uygulanacak.

Görevler projelerle ilişkilendirilebilecek.

## Aşama 5 — Ajanda / Takvim

- kullanıcının özel etkinlikleri,
- görev tarihleri,
- proje tarihleri,
- toplantılar,
- fuarlar

ortak takvim görünümünde bir araya getirilecek.

## Sonraki Modüller

- Ajanda / Notlar
- Ar-Ge / Toplantılar
- Ar-Ge / Fuarlar
- Ar-Ge / Bilgi Bankası

Baz platform oturduktan sonra:

- CRM
- ERP
- Satış
- Satın Alma
- diğer iş modülleri

geliştirilebilir.

---

## Durum

Bu doküman şu aşamada ürün geliştirme taslağıdır.

Kesin database schema, route adları, controller/model yapıları ve migration detayları mevcut OpenCore repository'si incelendikten ve ilgili geliştirme task'ı kararlaştırıldıktan sonra belirlenecektir.

Mevcut kanonik OpenCore mimarisi ve daha önce onaylanmış ADR/uygulama kararları bu doküman tarafından değiştirilmez.
