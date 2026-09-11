# OpenCore Baz Modüller ve Entegrasyon Taslağı

## Amaç

OpenCore; şirket içi operasyonel çalışma, koordinasyon ve karar destek platformudur.

Bu dokümanın amacı, OpenCore üzerinde geliştirilecek ortak baz modülleri ve bu modüller arasındaki temel ilişkileri tanımlamaktır.

OpenCore bir ERP veya CRM değildir. Gerektiğinde ERP, CRM, diğer dış sistemler, API'ler, yapay zekâ ajanları ve farklı veritabanlarıyla veri alışverişi yapabilir.

Bu doküman mevcut kanonik OpenCore mimarisini değiştirmez.

---

## Temel Yaklaşım

OpenCore'da üç farklı kavram birbirinden ayrı tutulacaktır:

### 1. Permission — Yetki

Mevcut `user_group` ve OpenCart/OpenCore permission mekanizması kullanılmaya devam eder.

`user_group`, kullanıcının hangi modül veya route'a erişebileceğini ve hangi işlemleri değiştirebileceğini belirler.

Örnek:

- `access`: kullanıcı ilgili modülü veya ekranı açabilir.
- `modify`: kullanıcı ilgili modülde değişiklik yapabilir.

### 2. Membership — Ekip Üyeliği

`Team / Ekip`, `user_group` ile aynı şey değildir.

Ekipler departman veya yetki grubu değildir. Belirli bir proje, çalışma veya amaç için farklı alanlardan kullanıcıların bir araya geldiği çapraz fonksiyonlu çalışma gruplarıdır.

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
- bir ekibe

atanabilir.

Assignment, permission ve team membership'ten ayrı bir kavramdır.

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

Diğer operasyonel modüller, bu ortak yapı oturduktan sonra ihtiyaç bazında ayrıca geliştirilebilir.

ERP, CRM ve benzeri dış sistemlerde bulunan veriler gerektiğinde entegrasyon yoluyla OpenCore içinde kullanılabilir; bu sistemlerin tamamını OpenCore içinde yeniden oluşturmak hedef değildir.

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

1. `user_group` üzerinden Projeler modülüne erişim izni kontrol edilir.
2. Gerekli permission varsa, kayıt görünürlüğü Team membership gibi ilgili kurallara göre sınırlandırılabilir.

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

İleride bir projeye birden fazla ekip bağlanması gerekebileceği için veri modeli gereksiz biçimde tek-ekip varsayımına kilitlenmemelidir. İlk sürüm minimum kapsamla geliştirilebilir.

---

# Ajanda → Görevler

Görevler sistemin ortak iş akışı modüllerinden biri olacaktır.

Bir görev:

- kullanıcıya,
- kullanıcı grubuna,
- ekibe

atanabilir.

Mantıksal görev atama tipleri:

```text
user
user_group
team
```

Kesin veri modeli implementation öncesinde tasarlanacaktır.

Gereksiz generic ACL veya relation framework kurulmayacaktır.

## Benim Görevlerim Mantığı

Kullanıcının görev listesinde aşağıdakilerden biri geçerliyse görev gösterilebilir:

- görev doğrudan kullanıcıya atanmışsa,
- görev kullanıcının `user_group` kaydına atanmışsa,
- görev kullanıcının üyesi olduğu bir ekibe atanmışsa.

---

# Görevlerin Diğer Modüllerle Entegrasyonu

Görevler yalnız bağımsız kayıtlar olmayacaktır.

Bir görev ilgili bir kayıtla bağlantılı olabilir:

- Proje
- Toplantı
- Fuar
- Bilgi Bankası kaydı
- firma veya kişi kaydı
- dış sistemden gelen ilgili operasyonel kayıt
- diğer OpenCore modülleri

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

Takvim iki temel veri tipini bir araya getirecektir.

## 1. Kullanıcının Kendi Etkinlikleri

Kullanıcı kendi takvim etkinliklerini oluşturabilir ve takip edebilir.

İleride gerekirse paylaşılabilir etkinlik davranışı ayrıca değerlendirilebilir.

## 2. Sistem Kaynaklı Takvim Kayıtları

Takvim diğer modüllerde bulunan tarihli kayıtları ortak görünümde gösterebilir.

Örnek kaynaklar:

- Görev son tarihleri
- Proje tarihleri / milestone'ları
- Toplantılar
- Fuarlar
- müşteri ziyaretleri
- teklif takip tarihleri
- sipariş teslim tarihleri
- diğer modüllerin veya entegre sistemlerin tarihli kayıtları

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

Bir not ileride:

- projeye,
- toplantıya,
- fuara,
- firmaya,
- başka bir operasyonel kayda

bağlanabilecek şekilde genişletilebilir.

İlk sürümde gereksiz generic paylaşım veya ACL sistemi kurulmayacaktır.

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
- firma ve kişi kayıtları

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

Bilgi Bankası kayıtları ileride:

- Projeler
- Fuarlar
- Firmalar
- Ürünler
- diğer operasyonel kayıtlar

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

---

# Tasarım İlkeleri

1. Mevcut OpenCore/OpenCart kullanıcı ve permission altyapısını gereksiz yere yeniden yazma.
2. `user_group` yetkilendirme için kullanılmaya devam etsin.
3. Team ayrı ve basit bir çalışma ilişkisi olarak tasarlansın.
4. Gereksiz generic ACL veya relation framework oluşturma.
5. Modüller birbirlerinin verilerini kopyalamak yerine ilişki kursun.
6. Yeni modüller mevcut notification, file, cron, user ve permission altyapısını mümkün olduğunca kullansın.
7. Ortak ihtiyaç ortaya çıkmadan soyut framework geliştirme.
8. Önce temel entity ve ilişkileri kur, sonra entegrasyonları ekle.
9. Dış sistemlere özgü kod OpenCore çekirdeğine gereksiz şekilde yayılmasın.
10. OpenCore'un amacı dış ERP/CRM sistemlerini yeniden üretmek değil, operasyonel çalışma katmanını oluşturmaktır.

---

# Önerilen Geliştirme Sırası

## Aşama 1 — Kullanıcı Yapısı Değerlendirmesi

Mevcut:

- `user`
- `user_group`
- permission sistemi
- session/auth ilişkileri

incelenecek.

Amaç mevcut yapıyı yeniden tasarlamak değil; Team ve sonraki modüller için gerekli minimum eklemeleri belirlemektir.

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

Ekip altyapısını kullanan ilk ortak modüllerden biri olacaktır.

## Aşama 4 — Ar-Ge / Projeler

Projeler ekiplerle ilişkilendirilecek.

Kullanıcıların ilgili projeleri görebilmesi için gerekli minimum membership filtering uygulanacak.

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

Baz platform oturduktan sonra diğer operasyonel modüller ve dış sistem entegrasyonları ihtiyaç bazında geliştirilebilir.

---

## Durum

Bu doküman aktif ürün geliştirme taslağıdır.

Kesin database schema, route adları, controller/model yapıları ve upgrade detayları ilgili geliştirme görevi başlamadan önce mevcut OpenCore repository'si incelenerek belirlenir.

Mevcut `AGENTS.md`, accepted ADR'ler ve kanonik OpenCore mimarisi bu dokümandan üstündür.
