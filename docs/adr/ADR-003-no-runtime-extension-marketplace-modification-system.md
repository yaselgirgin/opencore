# ADR-003: No Runtime Extension, Marketplace, or Modification System

- **Durum:** Accepted
- **Tarih:** 2026-08-21
- **Karar sahipleri:** Proje sahibi / teknik ekip
- **Hedef kod tabanı:** OpenCore

## 1. Bağlam

OpenCore, OpenCart tabanlı olsa da bir e-ticaret mağazası veya genel amaçlı runtime eklenti platformu değildir. Uygulama; ERP, CRM ve diğer şirket içi iş yeteneklerini kaynak kontrollü, ekip tarafından sahiplenilen kod olarak sunar.

OpenCart'tan kalan extension discovery, Marketplace, runtime installer/update ve OCMOD yüzeyleri bu hedef mimariyle uyumlu değildir. Bu mekanizmalar uygulama davranışının deploy edilmiş kaynak kod dışında değişebilmesine, ek route ve permission yüzeylerine ve üretim dosya sisteminde kod mutasyonuna izin verir.

ADR-001 stok e-ticaret işlevlerinin kaldırılmasını, ADR-002 ise Composer bağımlılıklarının repository/build sahipliğinde external vendor artifact olarak yönetilmesini kabul etmiştir. Bu karar, application feature delivery sınırını tamamlar.

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

## 3. Core Capability Promotion Politikası

Root `extension/` altında halen bulunan her capability önce aşağıdaki sınıflardan biriyle değerlendirilmelidir:

- `PROMOTE_TO_CORE`
- `DELETE`
- `TEMPORARILY_KEEP_DURING_MIGRATION`

Currency ile ilgili yetenekler, captcha/security yetenekleri veya gerçekten kullanılan başka built-in işlevler yalnız adlarına bakılarak silinmez. `PROMOTE_TO_CORE` seçilen capability'nin kesin hedef yolu dependency audit sonucunda belirlenir.

Promote edilen kod artık extension namespace, path, discovery veya loader contract'ına bağlı kalamaz. Compatibility wrapper veya extension alias bırakmak varsayılan çözüm değildir; böyle bir geçiş katmanı gerekiyorsa açık gerekçe, dar kapsam ve kaldırma koşulu ister.

## 4. Hedef Source Tree

Nihai source tree'de root `extension/` klasörü bulunmayacaktır. Kalıcı application bileşenleri ihtiyaca göre mevcut core alanlarında yer alır:

- `ocadmin/controller/`, `ocadmin/model/`, `ocadmin/language/`, `ocadmin/view/`
- `catalog/controller/`, `catalog/model/`
- `system/library/`, `system/config/`, `system/build/`
- mevcut native OpenCart-derived mimarinin gerekli diğer core klasörleri

Ayrı bir ADR olmadan service layer, repository layer, ORM, custom module loader, plugin loader veya yeni extension framework eklenmeyecektir.

## 5. Marketplace Kararı

Marketplace bir OpenCore capability'si değildir. Nihai durumda Marketplace admin menüsü ile controller, model, view ve language kaynakları; remote extension catalog; download/install/update akışları ve Marketplace filesystem mutation davranışı kaldırılacaktır.

External storage altında bulunabilecek tarihsel `marketplace/` dizininin runtime gereksinimi ayrı migration audit'inde değerlendirilir. Bu ADR herhangi bir yerel external-storage dizininin doğrudan veya körlemesine silinmesini emretmez.

## 6. OCMOD ve Modification Kararı

OpenCore OCMOD kullanmayacaktır. Dependency audit sonrasında aşağıdaki modification-specific yüzeyler kaldırılacaktır:

- modification administration,
- modification XML/application mekanizması,
- generated modification cache/tree,
- modification refresh,
- modification-specific startup ve config hook'ları,
- modification-specific event entegrasyonu.

Source removal öncesinde aktif controller, model, startup, event, generated runtime ve external-storage bağımlılıkları doğrulanmalıdır.

## 7. Event ve Startup Sınırı

Event sistemi OCMOD ile eş anlamlı değildir. Audit sırasında event registration ve action'lar şu şekilde sınıflandırılır:

- `CORE_EVENT`
- `EXTENSION_EVENT`
- `MODIFICATION_EVENT`
- `STALE_EVENT`

Yalnız extension veya modification bağımlı event yüzeyleri bu karar kapsamında cleanup adayıdır. Core event engine'in tamamen kaldırılması ayrı bir mimari karar gerektirir.

Startup sistemi de extension sistemiyle eş anlamlı değildir. Core startup controller ve action'ları korunabilir; extension, Marketplace veya modification-specific startup entry'leri dependency audit sonrasında kaldırılır.

## 8. Database ve External Storage Sınırı

Bu ADR database schema veya kayıt kaldırma kararı vermez. Extension, Marketplace veya modification ile ilişkili tablo ve kayıtlar için önce source/runtime dependency audit, ardından ayrı database kararı gerekir. Migration sırasında otomatik cleanup SQL veya DDL çalıştırılmaz. Şüpheli kalan dinamik kayıtlar `DB_DYNAMIC_STALE_RISK` olarak raporlanabilir.

Repository source cleanup ile external storage cleanup ayrı operasyonlardır. External storage içindeki Marketplace verisi, extension cache'i veya generated modification dosyaları source removal ile aynı commit içinde körlemesine silinmez; önce runtime bağımlılığı ve operasyonel sahipliği doğrulanır. Ortama özgü mutlak storage yolları bu mimarinin normative parçası değildir.

## 9. Release ve Deployment Modeli

Runtime extension installation bir production deployment yöntemi değildir. OpenCore release modeli şu sözleşmeyi korur:

```text
application source release
+
matching external Composer vendor artifact
```

Yeni feature delivery source-control commit, review ve release/deployment üzerinden yapılır. Production admin UI üzerinden kod kurulmaz veya güncellenmez.

## 10. Güvenlik ve Operasyonel Sonuçlar

Bu kararın uygulanması:

- runtime arbitrary extension installation yüzeyini,
- Marketplace supply-chain yüzeyini,
- OCMOD dynamic code mutation yüzeyini,
- gereksiz route ve permission yüzeylerini

azaltır. Application davranışının source-controlled olmasını, deploy determinism'i ve vendor artifact sahipliğinin açıklığını iyileştirir.

Bu faydalar sistemi kendiliğinden güvenli yapmaz. Kaynak incelemesi, dependency audit, access control, artifact doğrulaması, test ve güvenli deployment uygulamaları gerekli olmaya devam eder.

## 11. Migration Stratejisi

Migration audit sonuçlarına göre küçük, güvenli ve geri alınabilir batch'lere bölünür:

### Phase 1 - Inventory ve audit

Extension, Marketplace, modification, event, startup, permission, filesystem ve DB referansları envanterlenir.

### Phase 2 - Gerekli capability'lerin core'a promotion'ı

`PROMOTE_TO_CORE` yetenekler audit ile belirlenen core hedeflerine taşınır ve extension contract'larından ayrılır.

### Phase 3 - Kullanılmayan generic extension type yüzeyleri

Generic extension-type UI ve controller kaynakları bağımlılık doğrulamasıyla kaldırılır.

### Phase 4 - Marketplace, install, update ve discovery

Marketplace ile runtime install, uninstall, update ve extension discovery akışları kaldırılır.

### Phase 5 - OCMOD ve modification runtime

Modification-specific admin, application, cache, refresh, startup ve event yüzeyleri kaldırılır.

### Phase 6 - Root extension tree

Gerekli capability promotion'ları tamamlandıktan sonra root `extension/` tree kaldırılır.

### Phase 7 - Orphan entegrasyon artıkları

Config, startup, event, route ve permission artıkları dependency audit ile temizlenir.

### Phase 8 - External storage ve DB stale-state değerlendirmesi

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

## 13. ADR-002 ile İlişki

ADR-002 uyarınca root Composer graph repository/build tarafından sahiplenilir; Marketplace veya extension shared root `composer.json`, `composer.lock` ya da vendor graph'ını değiştiremez. Production Composer çalıştırmaz. Build ve deploy tooling `system/build/` altında tutulur; application release ile matching external vendor artifact birlikte seçilir ve deploy edilir.

ADR-003 bu sözleşmeyi application feature delivery açısından tamamlar: PHP dependency'leri reviewed Composer graph'tan üretilen artifact ile, business/application özellikleri ise source-controlled OpenCore core kodu ile teslim edilir. Runtime eklenti kurulumu bu iki sahiplik sınırından hiçbirinin alternatifi değildir.

## 14. Kabul ve Uygulama Koşulu

Bu ADR `Accepted` durumundadır. Her migration phase'i dependency envanteri, dar kapsamlı diff, test ve rollback sınırı gerektirir. Database veya external-storage temizliği bu belgenin kabulüyle otomatik olarak yetkilendirilmiş sayılmaz.
