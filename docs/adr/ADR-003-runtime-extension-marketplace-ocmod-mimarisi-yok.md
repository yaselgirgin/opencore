# ADR-003: Runtime Extension, Marketplace ve OCMOD Mimarisi Yok

- **Durum:** Accepted
- **Tarih:** 2026-08-21
- **Karar sahipleri:** Proje sahibi / teknik ekip
- **Hedef kod tabanı:** OpenCore

## 1. Bağlam

OpenCore, OpenCart tabanlı olsa da bir e-ticaret mağazası veya genel amaçlı runtime eklenti platformu değildir. Uygulama; ERP, CRM ve diğer şirket içi iş yeteneklerini kaynak kontrollü, ekip tarafından sahiplenilen kod olarak sunar.

OpenCart'tan kalan extension discovery, Marketplace, runtime installer/update ve OCMOD yüzeyleri bu hedef mimariyle uyumlu değildir. Bu mekanizmalar uygulama davranışının deploy edilmiş kaynak kod dışında değişebilmesine, ek route ve permission yüzeylerine ve üretim dosya sisteminde kod mutasyonuna izin verir.

ADR-001 stok e-ticaret işlevlerinin kaldırılmasını kabul etmiştir. Canonical dağıtım, kurulum, storage/vendor ve manual application update yaşam döngüsü ADR-006 tarafından tanımlanır. Bu karar, application feature delivery sınırını tamamlar.

## 2. Karar

OpenCore bir runtime plugin veya extension platformu olmayacaktır.

Yeni işlevler:

- yalnız OpenCore source tree içinde,
- ekip tarafından sahiplenilerek,
- source-control commit, review ve release sürecinden geçirilerek,
- mevcut `Controller -> Model -> Database` mimarisine doğrudan entegre edilerek

geliştirilecektir.

Nihai mimaride aşağıdaki mekanizmalar bulunmayacaktır:

- root `extension/` tree,
- OpenCart extension discovery,
- runtime extension install, uninstall ve update,
- Marketplace UI ile catalog, download ve install akışları,
- OCMOD ve runtime modification application,
- payment, shipping, total, fraud, feed, theme, report, analytics, other ve benzeri generic extension-type administration yüzeyleri,
- extension package tabanlı feature delivery,
- extension tarafından shared root Composer graph veya vendor artifact mutasyonu.

Bu karar migration'ın audit yapılmadan toplu silme şeklinde uygulanmasını onaylamaz.

## 3. Kabiliyetleri Çekirdeğe Taşıma Politikası

Root `extension/` altında halen bulunan her capability önce aşağıdaki sınıflardan biriyle değerlendirilmelidir:

- `PROMOTE_TO_CORE`
- `DELETE`
- `TEMPORARILY_KEEP_DURING_MIGRATION`

Currency ile ilgili yetenekler, captcha/security yetenekleri veya gerçekten kullanılan başka built-in işlevler yalnız adlarına bakılarak silinmez. `PROMOTE_TO_CORE` seçilen capability'nin kesin hedef yolu dependency audit sonucunda belirlenir.

Promote edilen kod artık extension namespace, path, discovery veya loader contract'ına bağlı kalamaz. Compatibility wrapper veya extension alias bırakmak varsayılan çözüm değildir; böyle bir geçiş katmanı gerekiyorsa açık gerekçe, dar kapsam ve kaldırma koşulu ister.

## 4. Hedef Kaynak Ağacı

Nihai source tree'de root `extension/` klasörü bulunmayacaktır. Kalıcı application bileşenleri ihtiyaca göre mevcut core alanlarında yer alır:

- `admin/controller/`, `admin/model/`, `admin/language/`, `admin/view/`
- `catalog/controller/`, `catalog/model/`
- `system/library/`, `system/config/`
- mevcut native OpenCart-derived mimarinin gerekli diğer core klasörleri

Ayrı bir ADR olmadan service layer, repository layer, ORM, custom module loader, plugin loader veya yeni extension framework eklenmeyecektir.

## 5. Marketplace Kararı

Marketplace bir OpenCore capability'si değildir. Nihai durumda Marketplace admin menüsü ile controller, model, view ve language kaynakları; remote extension catalog; download/install/update akışları ve Marketplace filesystem mutation davranışı kaldırılacaktır.

External storage altında bulunabilecek tarihsel `marketplace/` dizininin runtime gereksinimi ayrı migration audit'inde değerlendirilir. Bu ADR herhangi bir yerel external-storage dizininin doğrudan veya körlemesine silinmesini emretmez.

## 6. OCMOD ve Değişiklik Kararı

OpenCore OCMOD kullanmayacaktır. Dependency audit sonrasında aşağıdaki modification-specific yüzeyler kaldırılacaktır:

- modification administration,
- modification XML/application mekanizması,
- generated modification cache/tree,
- modification refresh,
- modification-specific startup ve config hook'ları,
- modification-specific event entegrasyonu.

Source removal öncesinde aktif controller, model, startup, event, generated runtime ve external-storage bağımlılıkları doğrulanmalıdır.

## 7. Olay ve Başlangıç Sınırı

Event sistemi OCMOD ile eş anlamlı değildir. Audit sırasında event registration ve action'lar şu şekilde sınıflandırılır:

- `CORE_EVENT`
- `EXTENSION_EVENT`
- `MODIFICATION_EVENT`
- `STALE_EVENT`

Yalnız extension veya modification bağımlı event yüzeyleri bu karar kapsamında cleanup adayıdır. Core event engine'in tamamen kaldırılması ayrı bir mimari karar gerektirir.

Startup sistemi de extension sistemiyle eş anlamlı değildir. Core startup controller ve action'ları korunabilir; extension, Marketplace veya modification-specific startup entry'leri dependency audit sonrasında kaldırılır.

## 8. Veritabanı ve Harici Depolama Sınırı

Bu ADR database schema veya kayıt kaldırma kararı vermez. Extension, Marketplace veya modification ile ilişkili tablo ve kayıtlar için önce source/runtime dependency audit, ardından ayrı database kararı gerekir. Migration sırasında otomatik cleanup SQL veya DDL çalıştırılmaz. Şüpheli kalan dinamik kayıtlar `DB_DYNAMIC_STALE_RISK` olarak raporlanabilir.

Repository source cleanup ile external storage cleanup ayrı operasyonlardır. External storage içindeki Marketplace verisi, extension cache'i veya generated modification dosyaları source removal ile aynı commit içinde körlemesine silinmez; önce runtime bağımlılığı ve operasyonel sahipliği doğrulanır. Ortama özgü mutlak storage yolları bu mimarinin normative parçası değildir.

## 9. Yayın ve Dağıtım Modeli

Runtime extension installation bir production deployment yöntemi değildir. OpenCore release modeli ADR-006'daki canonical sözleşmeyi korur:

```text
complete source-controlled OpenCore tree
+
distributed system/storage/vendor tree
```

Yeni feature delivery source-control commit, review ve stable source archive üzerinden yapılır. Production Admin UI üzerinden kod kurulmaz veya güncellenmez. Runtime ayrı vendor artifact indirme, aktivasyon veya swap işlemi yapmaz; external-storage kurulumlarında vendor senkronizasyonu ADR-006 uyarınca manual deployment sorumluluğudur.

## 10. Güvenlik ve Operasyonel Sonuçlar

Bu kararın uygulanması:

- runtime arbitrary extension installation yüzeyini,
- Marketplace supply-chain yüzeyini,
- OCMOD dynamic code mutation yüzeyini,
- gereksiz route ve permission yüzeylerini

azaltır. Application davranışının source-controlled olmasını, deploy determinism'i ve vendor bağımlılık sahipliğinin açıklığını iyileştirir.

Bu faydalar sistemi kendiliğinden güvenli yapmaz. Kaynak incelemesi, dependency audit, access control, artifact doğrulaması, test ve güvenli deployment uygulamaları gerekli olmaya devam eder.

## 11. Geçiş Stratejisi

Migration audit sonuçlarına göre küçük, güvenli ve geri alınabilir batch'lere bölünür:

### Faz 1 — Envanter ve Audit

Extension, Marketplace, modification, event, startup, permission, filesystem ve DB referansları envanterlenir.

### Faz 2 — Gerekli Kabiliyetlerin Çekirdeğe Taşınması

`PROMOTE_TO_CORE` yetenekler audit ile belirlenen core hedeflerine taşınır ve extension contract'larından ayrılır.

### Faz 3 — Kullanılmayan Genel Extension Türü Yüzeyleri

Generic extension-type UI ve controller kaynakları bağımlılık doğrulamasıyla kaldırılır.

### Faz 4 — Marketplace, Kurulum, Güncelleme ve Keşif

Marketplace ile runtime install, uninstall, update ve extension discovery akışları kaldırılır.

### Faz 5 — OCMOD ve Runtime Modification

Modification-specific admin, application, cache, refresh, startup ve event yüzeyleri kaldırılır.

### Faz 6 — Root Extension Ağacı

Gerekli capability promotion'ları tamamlandıktan sonra root `extension/` tree kaldırılır.

### Faz 7 — Sahipsiz Entegrasyon Artıkları

Config, startup, event, route ve permission artıkları dependency audit ile temizlenir.

### Faz 8 — Harici Depolama ve Eski DB Durumunun Değerlendirilmesi

External storage artıkları ve `DB_DYNAMIC_STALE_RISK` kayıtları ayrı operasyonel ve database kararlarıyla değerlendirilir.

## 12. Kapsam Dışı Konular

Bu ADR aşağıdaki kararları vermez:

- frontend build tool seçimi,
- replacement package/plugin framework,
- custom module system tasarımı,
- PHP upgrade'i,
- database schema redesign,
- event sisteminin tamamen kaldırılması,
- localisation veya currency capability kaldırılması,
- captcha veya security capability kaldırılması.

## 13. ADR-006 ile İlişki

ADR-006 uyarınca repository ve stable source archive complete dağıtım ağacıdır; runtime vendor `system/storage/vendor/` altında dağıtılır. Production ve end user Composer çalıştırmaz. Runtime, Marketplace veya extension mekanizması dependency graph'ını ya da vendor ağacını değiştiremez.

ADR-003 bu sözleşmeyi application feature delivery açısından tamamlar: dependency değişiklikleri reviewed source-controlled dağıtım ağacıyla, business/application özellikleri ise OpenCore core koduyla teslim edilir. Runtime eklenti kurulumu bu sahiplik sınırının alternatifi değildir.

## 14. Kabul ve Uygulama Koşulu

Bu ADR `Accepted` durumundadır. Her migration phase'i dependency envanteri, dar kapsamlı diff, test ve rollback sınırı gerektirir. Database veya external-storage temizliği bu belgenin kabulüyle otomatik olarak yetkilendirilmiş sayılmaz.
