# OpenCore AI ve Kurumsal Süreklilik Yol Haritası

- **Durum:** Aktif
- **Tarih:** 11 Eylül 2026
- **Kapsam:** OpenCore ürün yönü, AI kullanımı, kurumsal bilgi yönetimi ve operasyonel süreklilik

## 1. Amaç

Bu planın amacı OpenCore'un uzun vadede:

- şirketin günlük operasyonel çalışma platformu,
- kurumsal hafıza katmanı,
- karar destek altyapısı,
- kontrollü AI erişim noktası

olarak gelişmesini sağlamak ve sistem ile kritik operasyonel bilginin tek kişiye bağımlılığını azaltmaktır.

Ana prensip:

> Minimum karmaşıklık, maksimum operasyonel fayda.

Yeni teknoloji yalnızca gerçek bir iş problemini daha güvenilir, daha sürdürülebilir veya daha az müdahaleyle çözüyorsa kullanılmalıdır.

---

## 2. OpenCore'un Ürün Sınırı

OpenCore bir ERP değildir.

OpenCore; şirket içi operasyonel çalışma, koordinasyon ve karar destek platformudur.

Gerektiğinde:

- ERP sistemleri,
- diğer veritabanları,
- dış API'ler,
- AI servisleri,
- otomasyon araçları,
- araştırma araçları

ile veri alışverişi yapabilir.

Dış sistemlerde zaten doğru ve güvenilir biçimde bulunan işlevleri OpenCore içinde yeniden üretmek temel hedef değildir.

OpenCore'un başarısı modül sayısıyla değil, şirketin gerçek işini ne kadar kolay, hızlı, güvenilir ve az müdahaleyle yürüttüğüyle ölçülmelidir.

---

## 3. Mevcut Sistemden Geçiş Yaklaşımı

Şirketin eski iç sistemi uzun yıllara yayılan operasyonel ve ticari veri içerir.

Geçiş big-bang şeklinde yapılmayacaktır.

Kanonik yaklaşım:

1. OpenCore çekirdeğini sağlam tut.
2. Gerçek operasyonel sorun çözen yeni modülleri OpenCore'da geliştir.
3. Yeni modülleri kontrollü biçimde gerçek kullanımda doğrula.
4. Eski sistemde değerli olan modül ve verileri ihtiyaç sırasına göre taşı.
5. Her veri alanı için açık bir source of truth belirle.
6. Aynı verinin iki sistemde bağımsız ve çelişkili şekilde sahiplenilmesine izin verme.

Eski sistem yalnız "eski olduğu için" topluca yeniden yazılmaz.

Taşınacak her modülün gerçekten gerekli olup olmadığı ayrıca değerlendirilir.

---

## 4. Source of Truth ve Entegrasyon İlkesi

Her veri domaininin açık bir sahibi olmalıdır.

Örnek ayrım:

```text
Dış ERP / diğer sistemler
→ kendi resmi veya uzmanlık verisinin sahibi

OpenCore
→ şirkete özel operasyonel veri, workflow, görünürlük ve karar desteği

AI
→ yorumlama, ilişkilendirme, özetleme ve öneri

Vector / semantic index
→ yalnız retrieval yardımcı katmanı
```

AI, vector database veya otomasyon araçları gerçek iş verisinin sahibi değildir.

Dış sistem entegrasyonları mevcut OpenCore mimarisi içinde ayrık tutulmalı; sisteme özgü kod OpenCore çekirdeğine gereksiz biçimde yayılmamalıdır.

---

## 5. AI Kullanım İlkesi

Temel karar:

> Deterministik olarak çözülebilen iş AI ile çözülmez.

Aşağıdaki türde işler mümkün olduğunca normal application logic ile çözülmelidir:

- hesaplamalar,
- permission kontrolleri,
- workflow state geçişleri,
- stok veya miktar kontrolleri,
- sabit business rule'lar,
- SQL ile güvenilir biçimde üretilebilen sonuçlar,
- formül veya açık kuralla çözülebilen kararlar.

AI'ın değerli olduğu alanlar:

- büyük veri kümelerini yorumlama,
- geçmiş kayıtları ilişkilendirme,
- neden-sonuç analizi,
- örüntü ve fırsat bulma,
- özetleme,
- öneri oluşturma,
- yapılandırılmamış içerikleri anlama,
- doğal dil ile kurumsal bilgiye erişme,
- gerektiğinde internet araştırması.

Temel ayrım:

> OpenCore güvenilir gerçekleri ve kuralları üretir; AI bu gerçeklerden anlam çıkarır.

AI operasyonel kontrol mekanizmasının yerine geçmemelidir.

---

## 6. Öncelikli AI Kullanım Alanları

İlk değerli kullanım alanları özellikle geçmiş ve güncel iş verisinin birlikte yorumlanabildiği senaryolardır.

Örnekler:

- fuar sonrası müşteri ve fırsat analizi,
- geçmiş görüşmeler ile yeni görüşmelerin karşılaştırılması,
- teklif ve sipariş geçmişinin yorumlanması,
- kaybedilmiş işlerin ortak nedenlerinin bulunması,
- takip edilmesi gereken müşterilerin belirlenmesi,
- kurumsal Bilgi Bankası üzerinde doğal dil arama,
- operasyonel kayıtların özetlenmesi,
- yönetim için karar destek açıklamaları.

AI sonucu mümkün olduğunca dayandığı OpenCore kayıtları veya Bilgi Bankası kaynaklarıyla ilişkilendirilebilir olmalıdır.

---

## 7. Araç Seçim Sırası

Bir probleme çözüm seçerken en basit yeterli araç tercih edilir.

Kanonik karar sırası:

```text
1. Deterministik kod / SQL / mevcut OpenCore mekanizması yeterli mi?
   → Evetse onu kullan.

2. Basit bir otomasyon yeterli mi?
   → Gerekirse n8n veya benzeri workflow aracı kullan.

3. Tek AI çağrısı veya tek ajan yeterli mi?
   → Gereksiz multi-agent yapı kurma.

4. Kurumsal bilgi retrieval gerekli mi?
   → Önce mevcut OpenCore verisi ve Bilgi Bankası kullanılır.
   → İhtiyaç oluşursa semantic/vector retrieval katmanı kullanılabilir.

5. İnternet veya browser araştırması gerekli mi?
   → Uygun harici araştırma aracı kullanılabilir.

6. Görev gerçekten parçalanmalı, yön değiştirmeli veya delege edilmeli mi?
   → Ancak o zaman agent orchestration kullanılabilir.
```

Hiçbir harici AI, otomasyon veya orchestration aracı OpenCore mimarisinin zorunlu parçası olarak kabul edilmez.

Bu araçlar gerektiğinde OpenCore'u veya ilgili şirket operasyonlarını desteklemek için kullanılabilir.

OpenCore bu sistemlerden bağımsız olarak çalışabilmelidir.

---

## 8. Model Seçimi

Her iş için en güçlü modeli kullanmak hedef değildir.

Model seçimi:

- görev karmaşıklığı,
- risk,
- bağlam büyüklüğü,
- doğruluk ihtiyacı

üzerinden yapılmalıdır.

Basit veya tekrar eden işler mümkünse daha düşük maliyetli modellerle çözülebilir.

Kritik mimari kararlar, güvenlik, karmaşık business logic veya yüksek riskli değişikliklerde daha güçlü reasoning modeli kullanılabilir.

Model routing ayrı bir ürün mimarisi oluşturacak kadar karmaşık hale getirilmemelidir.

---

## 9. Bilgi Bankası

Bilgi Bankası OpenCore'un temel kurumsal süreklilik modüllerinden biri olacaktır.

Amaç yalnızca doküman saklamak değil, şirket bilgisini kişilerin hafızasından kurumsal sisteme taşımaktır.

Örnek bilgi alanları:

- üretim,
- baskı,
- CNC,
- bakım,
- temizlik,
- kalite,
- satış / ihracat,
- IT / OpenCore,
- makine ve ekipman kullanımı,
- ürün ve proses bilgisi,
- arıza ve sorun çözme,
- runbook'lar.

Bilgi Bankası bir klasör veya wiki mantığıyla sınırlı kalmamalıdır.

Bilgiler mümkün olduğunca yapılandırılmış metadata ile tutulmalıdır.

Örnek alanlar:

```text
Başlık
Kategori / Departman
Makine
Ürün
İşlem
Hazırlık
İşlem adımları
Kontrol
Sık yapılan hatalar
İlgili dokümanlar
Fotoğraf / video
Bilgi sahibi
Onaylayan
Son doğrulama
Sonraki gözden geçirme
Versiyon
Durum
```

Kesin schema ilgili modül geliştirilirken mevcut OpenCore mimarisi içinde belirlenir.

---

## 10. Bilginin Yaşam Döngüsü

Bilgi yalnız kaydedilmemeli; güncelliği de yönetilmelidir.

Kritik bilgi kayıtlarında mümkün olduğunca:

- bilgi sahibi,
- oluşturan,
- onaylayan,
- oluşturma tarihi,
- son güncelleme,
- son doğrulama,
- sonraki gözden geçirme,
- versiyon,
- durum

bulunmalıdır.

Örnek durumlar:

```text
Güncel
Gözden geçirilmeli
Geçersiz
```

AI, güncelliği belirsiz veya geçersiz bilgiyi güncelmiş gibi kullanmamalıdır.

---

## 11. Bilgilerin İlişkilendirilmesi

Bilgi Bankası kayıtları zamanla diğer OpenCore kayıtlarıyla ilişkilendirilebilir.

Örnek:

```text
Makine
├── kullanım prosedürü
├── bakım prosedürü
├── arıza kayıtları
├── yedek parçalar
└── ilgili videolar
```

```text
Ürün
├── üretim bilgisi
├── baskı
├── kalıp
├── CNC
├── kalite
└── paketleme
```

İlişkilendirme gerçek ihtiyaç kadar genişletilir.

Baştan ağır bir knowledge graph altyapısı kurulmaz.

---

## 12. AI + Bilgi Bankası

AI'ın Bilgi Bankası içindeki temel görevi yeni gerçek üretmek değil:

> mevcut şirket bilgisini bulmak, ilişkilendirmek ve açıklamaktır.

Örnek:

> "Bu üründe CNC işlemi için hangi takım kullanılmalı?"

veya:

> "Baskıda boya yüzeyden ayrılıyorsa hangi kontroller yapılmalı?"

AI ilgili:

- ürün,
- makine,
- proses,
- bakım,
- hata,
- prosedür

kayıtlarını bulup kullanıcıya anlamlı biçimde sunabilir.

Bu yapı geliştiğinde kontrollü bir Knowledge API veya benzeri retrieval katmanı eklenebilir.

---

## 13. Semantic / Vector Retrieval

Vector database ilk günden zorunlu değildir.

İhtiyaç oluşursa görev dağılımı şu şekilde olabilir:

```text
MySQL
→ gerçek bilgi ve ilişkiler

Vector / semantic index
→ benzer içerik ve doğal dil retrieval

AI
→ bulunan bilgiyi yorumlama ve açıklama
```

Vector database hiçbir zaman source of truth olmaz.

Semantik retrieval ancak bilgi hacmi ve kullanım ihtiyacı gerçekten oluştuğunda eklenir.

---

## 14. Teknik Dokümantasyon

Repository içindeki aktif dokümantasyon mevcut kanonik yapıyı takip eder:

```text
docs/
├── adr/
├── plans/
└── history/
```

Aktif mimari kararlar ADR'lerde tutulur.

Aktif geliştirme ve ürün yol haritaları `docs/plans/` altında tutulur.

Artık güncel olmayan fakat saklanması değerli belgeler `docs/history/` altında tutulur.

Göreve özel teknik dokümantasyon ancak gerçek ihtiyaç varsa eklenir.

Dokümantasyon kendi başına amaç değildir; sistemi başka birinin anlayabilmesini ve sürdürebilmesini sağlamalıdır.

---

## 15. Runbook Yaklaşımı

Kritik operasyonlar için gerektiğinde kısa ve uygulanabilir runbook'lar hazırlanmalıdır.

Örnek alanlar:

- OpenCore erişilemiyor,
- dış sistem bağlantısı kesildi,
- entegrasyon başarısız,
- backup restore gerekiyor,
- deployment yapılacak,
- database upgrade başarısız,
- credential değişikliği gerekiyor,
- background job çalışmıyor.

Runbook amacı:

> sistemi geliştirmemiş makul teknik bilgiye sahip başka bir kişinin problemi teşhis edip doğru müdahaleyi başlatabilmesi.

Runbook yalnız gerçek ve kritik operasyonlar için oluşturulmalıdır; her küçük iş için doküman üretilmez.

---

## 16. System Health

İleride yetkili kullanıcılar için merkezi System Health alanı geliştirilebilir.

Bu alan örneğin:

- OpenCore database durumu,
- dış sistem bağlantıları,
- background job durumu,
- son başarılı entegrasyon,
- son backup bilgisi,
- başarısız işler,
- application version,
- database version

gibi gözlemsel bilgileri gösterebilir.

System Health:

- updater değildir,
- deployment engine değildir,
- kendi başına sistemi değiştirmez.

Amaç sistemin black box olmasını azaltmaktır.

---

## 17. Operasyonel Sahiplik ve Tek Kişiye Bağımlılık

OpenCore ve şirketin kritik operasyonel bilgisi uzun vadede tek bir kişiye bağlı kalmamalıdır.

Hedef:

> Sistemin geliştirilmesi, işletilmesi, kurtarılması ve temel operasyonel bilginin anlaşılması başka kişiler tarafından da mümkün olmalıdır.

Uzun vadede en az bir ikinci kişinin:

- OpenCore'un temel yapısını,
- kritik entegrasyonları,
- deployment ve backup yaklaşımını,
- arıza halinde ilk kontrolleri,
- doğru teknik desteğe nasıl ulaşacağını

bilmesi hedeflenmelidir.

Bu kişinin sistemi sıfırdan geliştirebilecek senior developer olması şart değildir.

---

## 18. Operational Continuity Çalışma Alanı

OpenCore roadmap'inde **Operational Continuity** sürekli bir çalışma alanı olarak ele alınır.

Zaman içinde aşağıdaki işler değerlendirilebilir:

- ADR disiplinini sürdürme
- aktif `docs/` yapısını temiz tutma
- kritik business rule'ları dokümante etme
- gerekli runbook'ları hazırlama
- backup / restore prosedürlerini doğrulama
- deployment / recovery dokümantasyonu
- System Health ekranı
- Bilgi Bankası modülü
- bilgi versiyonlama ve doğrulama
- makine / proses / ürün ilişkileri
- dosya, fotoğraf ve video ekleri
- kontrollü Knowledge API
- gerektiğinde semantic/vector retrieval
- ikinci teknik/operasyonel sahip yetiştirme
- belirli aralıklarla bağımsız recovery / deployment doğrulaması

Bu başlıkların tamamı aynı anda yapılmaz.

Öncelik gerçek risk ve operasyonel faydaya göre belirlenir.

---

## 19. Uygulama Aşamaları

### Aşama 1 — Temel Dokümantasyon ve Mimari Disiplin

- aktif ADR'leri güncel tut
- plan/history ayrımını koru
- temel geliştirme kurallarını `AGENTS.md` altında tut
- gereksiz veya tarihsel talimatların aktif dokümanlara karışmasını önle

### Aşama 2 — Operasyonel Modüller

- OpenCore'un gerçek günlük kullanımını artıran baz modülleri geliştir
- eski sistemden yalnız gerekli modül ve verileri kontrollü taşı
- source-of-truth sınırlarını koru

### Aşama 3 — Bilgi Bankası

- temel Bilgi Bankası modülünü geliştir
- metadata ve bilgi yaşam döngüsünü kur
- dosya / görsel / video ilişkilerini destekle
- ürün / makine / proses ilişkilerini ihtiyaç kadar ekle

### Aşama 4 — AI Destekli Erişim

- Bilgi Bankası ve operasyonel verilere kontrollü AI erişimi
- doğal dil retrieval
- özetleme ve ilişkilendirme
- kaynak gösterilebilir cevaplar
- gerektiğinde semantic retrieval

### Aşama 5 — Kurumsal Süreklilik

- gerekli runbook'ları tamamla
- System Health ihtiyacını uygula
- ikinci operasyonel sahibi yetiştir
- doküman ve recovery süreçlerini gerçek tatbikatlarla doğrula

---

## 20. Kapsam Dışı

Bu yol haritası aşağıdakileri hedeflemez:

- yeni bir ERP geliştirmek,
- mevcut dış sistemlerin tamamını OpenCore içinde kopyalamak,
- AI'ı temel business rule motoruna dönüştürmek,
- her süreci multi-agent yapmak,
- tüm süreçleri n8n'e taşımak,
- baştan knowledge graph platformu kurmak,
- ihtiyaç oluşmadan vector database eklemek,
- teknoloji kullanmış olmak için yeni teknoloji eklemek.

---

## 21. Nihai Tasarım Prensipleri

1. İş problemi teknolojiden önce gelir.
2. OpenCore ERP değildir; operasyonel çalışma ve karar destek platformudur.
3. Her veri domaininin açık bir source of truth'u olmalıdır.
4. Deterministik problem AI ile çözülmez.
5. AI yorumlama, ilişkilendirme, retrieval ve araştırma için kullanılır.
6. En basit yeterli araç tercih edilir; tek ajan yeterliyse multi-agent kurulmaz.
7. AI, vector DB ve otomasyon araçları gerçek şirket verisinin sahibi değildir.
8. Şirket bilgisi kişilerin hafızasından kurumsal sisteme taşınmalıdır.
9. Bilginin içeriği kadar owner'ı, versiyonu ve güncelliği de önemlidir.
10. OpenCore başka kişiler tarafından sürdürülebilecek şekilde geliştirilmelidir.

---

## 22. Başarı Ölçütü

Bu yol haritasının başarısı yeni teknoloji sayısıyla ölçülmez.

Başarı:

- günlük işin daha kolay yürütülmesi,
- tekrar eden manuel işin azalması,
- bilgiye erişimin hızlanması,
- karar kalitesinin artması,
- kritik bilginin kaybolmaması,
- sistemin tek kişiye bağımlılığının azalması

ile ölçülür.

Uzun vadeli hedef:

> OpenCore'un şirketin günlük çalışma ve kurumsal hafıza platformu haline gelmesi; AI ve diğer araçların ise yalnız gerçek ihtiyaç olduğunda bu platformu güçlendirmesi.
