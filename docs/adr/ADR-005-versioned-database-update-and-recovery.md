# ADR-005: OpenCore Versioned Database Update and Recovery Contract

- **Durum:** Accepted
- **Tarih:** 2026-08-25
- **Karar sahipleri:** Proje sahibi / teknik ekip
- **Hedef kod tabanı:** OpenCore

## 1. Bağlam

ADR-004, OpenCore release updater için doğrulanmış artifact, external staging, application/vendor backup, journal, rollback ve recovery sözleşmesini kabul eder. Mevcut updater, `manifest.json` içindeki kapalı `database.required` ve `database.updates` alanlarını doğrular; ancak DB değişikliği isteyen release'i `DATABASE_UPDATE_NOT_SUPPORTED` ile mutation öncesinde reddeder.

Kurulu updater gelecekteki bütün DB değişikliklerini önceden bilemez. Ayrıca aynı PHP isteğinde yüklenmiş sınıflar target release sınıflarıyla güvenli biçimde yeniden tanımlanamaz. Filesystem, external vendor ve MariaDB tek bir atomic transaction oluşturmaz. Özellikle DDL işlemleri implicit commit üretebilir ve normal transaction rollback garantisine sahip değildir.

Bu ADR, DB-changing release'ler için target-release-owned, ikinci istekle devam eden açık ve versioned bir sözleşme tanımlar. OpenCore mimarisi `Controller -> Model -> Database` olarak kalır.

## 2. Karar

DB-changing release akışı aşağıdaki sırayı kullanır:

1. Source updater target release'i ve manifesti doğrular.
2. `database.required = true` ise doğrulanmış full logical DB backup oluşturulur.
3. Application dosyaları `system/version.php` hariç aktive edilir.
4. Gerekliyse vendor aktive edilir ve doğrulanır.
5. Durable updater state `DATABASE_PENDING` olur.
6. Mevcut PHP isteği sona erer.
7. Yeni istek target-release source/vendor ile normal OpenCore bootstrap ve Loader üzerinden açılır.
8. Target Upgrade Model, manifestteki exact versioned DB update identifier'larını kendi allowlist'i üzerinden çalıştırır.
9. DB postcondition'ları doğrulanır.
10. System-owned `database_version` target sürüme ilerletilir.
11. `system/version.php` en son target sürüme ilerletilir.
12. State `APPLIED` olur ve updater request gate/lock kaldırılır.

Source updater arbitrary future DB değişikliklerini bilmek zorunda değildir. Target release, kendi explicit DB update Model kodunun sahibidir.

## 3. Legacy Installer Kullanılmaması

Target release'in DB update koduna sahip olması ilkesi kullanılır; OpenCart installer mimarisi geri getirilmez. Aşağıdakiler yasaktır:

- `install/` veya `install/index.php`,
- `upgrade_1`, `upload/install/` veya installer handoff,
- migration directory discovery veya "run every migration",
- arbitrary SQL/PHP script çalıştırma,
- Marketplace, extension veya OCMOD tabanlı update.

DB update native Admin Controller ve Model akışının parçasıdır.

## 4. Database Version Otoritesi

DB schema seviyesinin canonical metadata marker'ı `oc_setting` içinde tutulur:

```text
code  = system
key   = database_version
value = YYYY.MM.RELEASE
```

Bu row system-owned metadata'dır. Settings UI alanı değildir; normal Settings Save tarafından gösterilmez, POST edilmez veya güncellenmez. Yalnız native Upgrade DB akışı, bütün DB postcondition'ları başarıyla doğrulandıktan sonra değeri ilerletebilir.

Normal steady state:

```text
system/version.php == system/database_version
```

Unresolved DB-changing update sırasında iki değer bilinçli olarak farklı olabilir. Application dosyalarının aktive edilmesi tek başına `database_version` değerini ilerletmez.

## 5. Version İlerletme Sırası

Zorunlu sıra:

```text
preconditions
-> verified backup evidence
-> explicit DB operations
-> postconditions
-> database_version target
-> system/version.php target LAST
```

DDL kısmen commit olmuş fakat target postcondition tamamen kanıtlanmamışsa `database_version` source sürümde kalır. Kısmi schema target sürüm olarak işaretlenmez.

## 6. Updater Request Gate

Request gate otoritesi DB değil, `DIR_STORAGE/updates/` altındaki durable filesystem updater state'tir. DB kısmen değişmiş, erişilemez veya recovery gerektiriyor olabilir; gate kararı normal uygulama DB sorgusuna bağlı olamaz.

Gate gerektiren durumlar en az şunlardır:

- `DATABASE_PENDING`,
- `DATABASE_APPLYING`,
- `DATABASE_RECOVERY_REQUIRED`,
- `DATABASE_RESTORE_REQUIRED`,
- `DATABASE_RESTORE_FAILED`,
- `ROLLBACK_FAILED`,
- diğer unresolved updater durumları.

`config_maintenance` geri getirilmez.

## 7. Gate Davranışı

Unresolved DB-changing update sırasında:

- normal Admin route'ları bloklanır,
- normal API route'ları güvenli JSON `503 Service Unavailable` alır,
- cron bloklanır.

İzin verilen Admin yüzeyi yalnız ihtiyaç kadar dardır:

- gerekli login/authentication,
- `tool/upgrade` continuation,
- explicit DB execution,
- recovery/restore,
- gerektiğinde logout.

Bütün mutation endpoint'leri POST, CSRF token ve `modify:tool/upgrade` izni ister. Raw journal, path, SQL ve filesystem hata ayrıntıları kullanıcıya verilmez.

## 8. Session ve İkinci İstek

DB update, target dosyaları aktive eden aynı PHP isteğinde çalıştırılmaz. Mevcut istek biter; ikinci istek target source/vendor ve normal Loader ile yeni sınıfları yükler. Zaten geçerli olan Admin session kullanılabilir; otomatik logout zorunlu değildir.

Gate sırasında login gerekiyorsa başarılı login Upgrade continuation ekranına yönlendirebilir. Login hiçbir zaman side effect olarak schema-changing SQL çalıştırmaz.

## 9. Boot Compatibility

DB-changing target release, `compatible_source_versions` içinde ilan ettiği her source DB sürümü için dar Upgrade bootstrap/auth/recovery yolunu DB update tamamlanana kadar uyumlu tutmalıdır. Normal uygulama route'larının eski DB ile uyumlu olması gerekmez; bu route'lar gate tarafından bloklanır.

Boot compatibility release contract'ın zorunlu parçasıdır ve artifact yayınlanmadan önce test edilir.

## 10. Target-Owned Explicit Update Models

Target source explicit versioned DB update Model'larını içerir. Kavramsal route örneği:

```text
tool/upgrade/database/update_2026_08_2_001
```

Exact file, namespace ve class mevcut OpenCore Model Loader convention'ına uyar. Target Upgrade Model, identifier-to-model eşleşmesini açık allowlist ile tanımlar.

Manifest verisi doğrudan class adı veya filesystem path üretemez. Filesystem scanning, migration enumeration, generic registry ve automatic discovery yoktur.

## 11. Manifest Database Contract

Mevcut kapalı şekil korunur:

```json
{
  "database": {
    "required": true,
    "updates": ["2026.08.2.001"]
  }
}
```

Manifest yalnız identifier taşır; SQL, PHP, script body veya DB logic URL'si taşıyamaz. Identifier formatı implementation phase'inde exact, kapalı ve versioned hale getirilir.

Exact identifier biçimi `<step-version>.NNN` olur. `step-version`, canonical
`YYYY.MM.RELEASE` biçimindedir; `NNN` tam üç basamaklı ve en az `001` olur.
Step version source `database_version` değerinden büyük, final target version
değerinden küçük veya ona eşit olmalıdır. Identifier listesi önce step version
numeric bileşenlerine, sonra aynı step içindeki sequence değerine göre strictly
increasing olmak zorundadır.

Şunlar reddedilir:

- malformed veya duplicate identifier,
- target allowlist'inde olmayan identifier,
- target release'in sahip olmadığı identifier,
- yanlış sıra,
- source/target ile uyumsuz sequence.

## 12. Cumulative Release Politikası

OpenCore cumulative target package kullanır. Örneğin `2026.08.1 -> 2026.08.3` doğrudan destekleniyorsa 2026.08.3 manifesti gerekli handler'ları exact sırayla ilan eder; 2026.08.3 target source bunların implementasyon ve allowlist sahibidir.

Bu durumda target manifest ve allowlist ara step identifier'larını açıkça
içerebilir. Örneğin `2026.08.1 -> 2026.08.3` zinciri
`2026.08.2.001`, `2026.08.2.002`, `2026.08.3.001` identifier'larını
kullanabilir. Encoded version final target zorunluluğu taşımaz; DB step version'ı
ifade eder. Buna rağmen bütün handler eşleşmeleri final target release'in exact
allowlist'inden gelir; scanning veya automatic discovery yapılmaz.

`compatible_source_versions` doğrudan upgrade desteğinin otoritesidir. Historical migration directory discovery yapılmaz.

## 13. Handler Contract

Her versioned DB update Model aşağıdakileri açıkça sahiplenir:

- exact source ve diğer read-only precondition'lar,
- exact tablo/kolon/data kapsamı,
- transaction classification,
- DB işlemleri,
- affected-row ve schema/data postcondition'ları,
- idempotency,
- interruption/retry davranışı,
- failure ve recovery statement.

Handler keyfi SQL kabul etmez ve schema DSL oluşturmaz. Exact table/column/index existence veya scalar assertion gibi küçük yardımcılar yalnız generic migration engine'e dönüşmedikleri sürece kullanılabilir. `system/helper/db_schema.php` migration engine değildir.

## 14. Transaction Politikası

Runtime MySQLi/MariaDB'dir. Transaction-safe InnoDB DML, aynı DB connection üzerinde explicit olarak şunu kullanabilir:

```text
START TRANSACTION
COMMIT
ROLLBACK
```

Her handler çalışmasını şu sınıflardan biriyle ilan eder:

- `TRANSACTIONAL_DML`,
- `NON_TRANSACTIONAL_DDL`,
- ayrı review edilmiş mixed contract.

`CREATE`, `ALTER`, `DROP`, `RENAME`, `TRUNCATE` ve diğer implicit-commit üreten DDL için transactional rollback garantisi verilmez.

## 15. Database Backup Contract

DB-changing release, DB mutation öncesi verified full logical backup gerektirir. Retained Admin Backup/Restore yalnız row-level `TRUNCATE/INSERT` çıktısı sağladığı ve tam DDL recovery sözleşmesi olmadığı için updater backup'ı olarak yeterli değildir.

Backup release workspace içinde tutulur:

```text
DIR_STORAGE/updates/<version>/backup/database/
```

Evidence en az şunları içerir:

- database adı,
- source OpenCore version,
- source `database_version`,
- target version,
- ordered update identifiers,
- creation timestamp,
- backup format version ve DB prefix,
- database server driver/version,
- base-table/object inventory,
- structured component path/size/SHA-256 inventory,
- terminal validation status.

Backup yoksa, boşsa, okunamıyorsa veya hash doğrulanamıyorsa DB mutation başlamaz.

## 16. Backup Provider

Core OpenCore DB backup/restore PHP/MySQLi-native'dir ve normal OpenCore database connection üzerinden çalışır. Shell/process capability, SSH, `proc_open`, `exec`, `mysqldump`, `mysql` client veya executable path configuration core updater contract'ının parçası değildir.

Authoritative ownership native `Admin Model Tool Backup` içindedir. Updater ve Manual Admin Backup/Restore aynı structured backup/restore contract'ını kullanır; release manifest SQL, backup path veya executable seçemez. SQL yalnız validated structured backup'tan üretilen user portability export'udur.

Canonical internal backup formatı source-controlled ve streaming'dir:

```text
backup/database/
    metadata.json
    schema.ndjson
    data/<table>.ndjson
    evidence.json
```

Manual backup aynı layout ile `DIR_STORAGE/backup/<backup-id>/` altında saklanır ve doğrudan structured component'lerden restore edilir. Download sırasında validated structured backup'tan standalone `.sql` stream üretilir; SQL kalıcı internal restore authority değildir. Historical data-only `.sql` dosyaları history/download/delete akışında korunur; güvenli legacy restore davranışı updater trust boundary'sine dahil edilmeden devam edebilir.

Kurallar:

- schema actual database state'ten `SHOW CREATE TABLE` ile alınır; repository schema'dan üretilmez,
- rows primary-key order'da bounded batches halinde stream edilir,
- `NULL` ayrı typed record olarak, diğer scalar/UTF-8/binary bytes base64 olarak taşınır,
- JSON/NDJSON component'leri PHP serialization veya executable PHP içermez,
- restore yalnız hash ve inventory doğrulanmış structured component'leri MySQLi üzerinden uygular; generic SQL parser olmaz,
- metadata database identity, prefix, source/DB/target versions, ordered update identifiers, server identity ve object/table inventory içerir,
- evidence metadata, schema ve her table data component'inin path, byte size ve SHA-256 değerini doğrular; missing, modified ve undeclared component fail closed reddedilir,
- temporary workspace yalnız tam doğrulamadan sonra atomic directory rename ile aktive edilir,
- mevcut verified backup körlemesine overwrite edilmez,
- core format base tables'ı destekler; view, trigger, routine, event veya primary key'siz table görülürse complete backup oluşturulmaz ve updater fail closed kalır,
- restore yalnız fixed updater workspace'teki doğrulanmış structured OpenCore formatını kabul eder; generated veya uploaded SQL updater recovery input'u değildir.

Native client tabanlı alternatif provider ancak gelecekte ayrı ADR ile optional optimization olarak değerlendirilebilir; core correctness ona bağlanamaz.

## 17. Failure ve Recovery Politikası

### Transactional DML failure

1. Aynı connection rollback edilir.
2. Source DB precondition/post-state yeniden doğrulanır.
3. Source state doğrulanmışsa application/vendor rollback yapılabilir.
4. Doğrulama başarısızsa `DATABASE_RECOVERY_REQUIRED` durumuna geçilir.

### Non-transactional veya partial DDL failure

1. DB rollback yapılmış gibi raporlanmaz.
2. Source application/vendor körlemesine geri alınmaz.
3. Target recovery code, DB backup ve journal korunur.
4. VERSION ve `database_version` kanıtlanmadan ilerletilmez.
5. State `DATABASE_RECOVERY_REQUIRED`, `DATABASE_RESTORE_REQUIRED` veya `DATABASE_RESTORE_FAILED` olur.

Restore/recovery başarıyla doğrulanmadan gate ve updater lock kaldırılmaz.

Full logical restore MySQL/MariaDB DDL implicit-commit kuralları nedeniyle atomic değildir. Restore başlamadan evidence/hash/database identity yeniden doğrulanır ve durable state sırasıyla `DATABASE_RESTORE_REQUIRED` -> `DATABASE_RESTORING` -> `DATABASE_RESTORED` olur. Herhangi bir failure `DATABASE_RESTORE_FAILED` bırakır; backup, journal, updater lock ve request gate korunur. `DATABASE_RESTORED` başarılı physical restore kanıtıdır, application/DB compatibility kanıtı değildir ve tek başına gate/lock kaldırmaz.

Full logical DB restore, `oc_setting` içindeki system-owned
`database_version` satırını backup'ta bulunduğu haliyle restore eder. Restore
kodu bu değeri deployed application `VERSION` değeriyle eşleştirmek için
otomatik olarak yeniden yazmaz. Örneğin `system/version.php = 2026.08.3` ve
`database_version = 2026.08.1` durumu application/DB contract mismatch'tir ve
native Upgrade/Recovery uyumluluğu yeniden kanıtlayana kadar fail closed kalır.

Bu compatibility guard, `DIR_STORAGE/updates/` durable state gate'inden ayrı
bir sinyaldir. Durable state gate DB erişilemese bile pending/interrupted update'i
tespit eder. Compatibility guard ise aktif unresolved updater state yokken
`VERSION` ile system-owned `database_version` değerini karşılaştırır. DB marker'ın
eksik olması da, ayrı onaylı legacy baseline initialization dışında, unresolved
uyumluluk durumudur. `DATABASE_PENDING` handoff sırasında target application
dosyaları aktif olsa bile hem `VERSION` hem `database_version` bilinçli olarak
source version'da kalır; bu nedenle filesystem state otoritesi korunur.

Full-restore veya out-of-band mismatch durumunda aktif updater manifest/state
snapshot'ı bulunmayabilir. Bu durumda target Upgrade Model, desteklediği exact
source `database_version` değerlerini source-controlled recovery plan'larında
ilan eder. Her plan ordered `<step-version>.NNN` identifier listesidir ve her
identifier aynı target source içindeki exact handler allowlist'inde bulunmak
zorundadır. Plan key'i bulunamazsa kısmi handler subset'i çıkarılmaz; recovery
fail closed kalır. Directory scanning, filesystem discovery ve manifest dışında
handler türetme yapılmaz.

Aktif `DATABASE_PENDING` handoff identifier'larını validated durable manifest
snapshot'ından alır. Durable updater state bulunmayan restore mismatch ise
identifier'larını yalnız target-owned recovery plan'ın exact source-version
entry'sinden alır; sırf mismatch bulunduğu için sahte `DATABASE_PENDING`
oluşturulmaz. `database_version > VERSION` downgrade, reverse migration veya
metadata rewrite başlatmaz ve operator recovery gerektirir.

Desteklenen forward restore-recovery plan'ı explicit olarak başlatıldığında,
herhangi bir DB handler çalışmadan önce restored current DB için yeni bir full
logical backup oluşturulup Phase 3E-B evidence contract'ıyla doğrulanır. Eski
restore kaynağı bu yeni pre-mutation recovery backup'ının yerine geçmez.

## 18. Filesystem ve DB Atomicity

Filesystem, vendor ve MariaDB tek bir atomic transaction değildir. Journal her boundary'nin tamamlanma durumunu ayrı kaydeder. Filesystem başarısı DB başarısı değildir; DB failure da filesystem rollback'in her zaman güvenli olduğu anlamına gelmez.

Recovery kararı handler transaction classification, DB postcondition, backup evidence ve filesystem/vendor journal kanıtlarının birlikte değerlendirilmesiyle verilir; timestamp tek başına yeterli değildir.

## 19. Final Ordering

DB-changing release için bağlayıcı sıra:

```text
validate everything
-> acquire updater lock and request gate
-> create and verify application/vendor/DB backup evidence
-> apply application except VERSION
-> apply and verify vendor if declared
-> persist DATABASE_PENDING
-> end current request
-> boot target code in a new request
-> verify handler allowlist and DB preconditions
-> persist DATABASE_APPLYING
-> execute explicit DB operations
-> verify DB postconditions
-> advance database_version
-> apply system/version.php LAST
-> final runtime verification
-> persist APPLIED
-> release gate and lock
```

## 20. Recovery Ownership

Target application/vendor aktive edildikten sonra DB continuation ve recovery'nin sahibi target updater code'dur. Durable filesystem state source/target version, handler identifiers, backup identity, completed boundaries ve recovery state'i taşır. `system/version.php` source sürümü gösterirken target recovery bootstrap çalışabilir.

## 21. Security ve Trust Boundary

Yalnız SHA-256 doğrulanmış release manifestindeki identifier'lar değerlendirilir. Identifier'lar target Upgrade Model allowlist'i üzerinden çözülür; manifest arbitrary SQL, path veya class seçemez.

ADR-004'te belgelenen GitHub repository/account trust limitation değişmez. Hash doğrulama bağımsız publisher authentication değildir.

## 22. Non-Goals

Bu ADR aşağıdakileri oluşturmaz:

- generic migration framework veya migration table,
- ORM, repository veya service layer,
- schema DSL veya schema diff engine,
- installer application,
- runtime plugin/extension sistemi,
- SQL-in-manifest,
- ayrı indirilen DB scriptleri,
- automatic historical migration discovery.

## 23. Phase 3E-B Sınırı

İlk implementation phase yalnız şunları kapsar:

- system-owned `database_version` metadata contract,
- updater-specific request gate,
- strengthened identifier validation,
- target-owned explicit handler allowlist plumbing,
- PHP/MySQLi-native structured backup/restore, component evidence ve SQL download export,
- durable DB state/journal,
- safe second-request continuation.

Fake production migration eklenmez. İlk gerçek schema-changing handler, gerçek ve ayrıca onaylanmış bir OpenCore schema değişikliğiyle birlikte ayrı review edilir. Testler yalnız `opencore_test` içinde geçici, açık isimli ve sonunda tamamen kaldırılan fixture tablolar kullanabilir.

## 24. Sonuçlar

### Olumlu

- Future DB logic target release tarafından source-controlled olarak sağlanır.
- Source updater gelecekteki migration ayrıntılarını bilmez.
- DB, filesystem ve vendor boundary'leri dürüstçe ayrı izlenir.
- DDL partial-commit recovery görünür ve fail-closed olur.
- Legacy installer veya generic migration sistemi geri gelmez.

### Maliyet ve sınırlar

- DB-changing release build'i boot compatibility kanıtı ister.
- Deployment normal DB metadata/data privileges ve writable external updater storage sağlamalıdır.
- DB update sırasında normal Admin/API/Cron erişimi gate edilir.
- Non-transactional DDL failure otomatik rollback yerine verified restore veya açık recovery gerektirebilir.
- Her gerçek DB handler kendi contract ve review'una ihtiyaç duyar.
