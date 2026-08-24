# ADR-004: OpenCore Native Release Updater

- **Durum:** Accepted
- **Tarih:** 2026-08-24
- **Karar sahipleri:** Proje sahibi / teknik ekip
- **Hedef kod tabanı:** OpenCore

## 1. Bağlam

OpenCore üretimde çalışan, önceden provision edilmiş ve storefront içermeyen bir iç uygulama platformudur. Admin tarafındaki `Maintenance -> Upgrade` ekranı kalıcı bir core üretim yeteneğidir. Ekran yalnız bilgi veren bir sayfaya dönüştürülmeyecek; onaylı OpenCore sürümlerini güvenli ve denetlenebilir biçimde kuracaktır.

Mevcut `tool/upgrade` uygulaması OpenCart upgrade API'sine, OpenCart GitHub release'lerine, `opencart-{version}` arşiv adına, `upload/install/` dizinine ve `install/index.php?route=upgrade/upgrade_1` akışına bağlıdır. OpenCore installer uygulaması kalıcı olarak kaldırılmıştır. Bu sözleşmeler hem erişilemez hem de ADR-003 ile kabul edilen kaynak kontrollü, extension/Marketplace içermeyen mimariye aykırıdır.

Mevcut sürüm bilgisi tekil değildir. `index.php` ve `ocadmin/index.php` ayrı ayrı `VERSION = 4.1.0.3` tanımlar. `cron.php` kendi sürüm otoritesine sahip değildir. Bu durum release karşılaştırması için uygun bir canonical kaynak oluşturmaz.

`system/helper/db_schema.php` üretim migration motoru değildir. Şema tanımlama/oluşturma davranışı, çalışan bir veritabanına genel veya yıkıcı upgrade uygulamak için kullanılmayacaktır.

## 2. Karar

OpenCore, kendi release sözleşmesini kullanan native bir admin updater geliştirecektir. Ana akış:

1. `ocadmin/controller/tool/upgrade.php` kullanıcı isteğini, permission kontrollerini ve UI/JSON response orchestration'ını yönetir.
2. Yeni `ocadmin/model/tool/upgrade.php` release metadata erişimini, indirme, doğrulama, staging, backup/restore koordinasyonu, dosya uygulama ve açıkça onaylanmış DB update çağrılarını yönetir.
3. Twig ve language dosyaları yalnız sunum metni ve durum gösterir.
4. Paket canlı tree'ye doğrudan açılmaz; önce external storage altında doğrulanır.
5. Mutasyon yalnız `modify:tool/upgrade` izni ve açık administrator onayıyla başlar.
6. Updater hiçbir aşamada `install/` uygulamasına redirect etmez.

Bu karar service/repository layer, ORM, Marketplace installer, OCMOD, runtime extension sistemi veya genel migration framework oluşturmaz. Mimari `Controller -> Model -> Database` olarak kalır.

## 3. Release Kaynağı

İlk metadata ve artifact kaynağı, `yaselgirgin/opencore` repository'sinin GitHub Releases alanıdır. Kaynak kontrollü policy şu değerleri belirler:

- application/repository identity,
- desteklenen manifest contract sürümleri,
- release asset adlandırma kuralı,
- izin verilen update kanalı,
- zorunlu hash sidecar biçimi.

İlk asset adlandırması `opencore-release-{version}.zip` ve `opencore-release-{version}.zip.sha256` olacaktır. OpenCart endpoint'i veya paket adı kullanılmaz.

HTTP timeout, proxy, GitHub API base URL veya kurumsal mirror gibi deployment ayrıntıları environment/local configuration ile değiştirilebilir. Admin tarafından serbest repository veya download URL girilemez. Configurability, release kimliğini ve trust boundary'yi genişletemez.

## 4. Sürüm Sözleşmesi ve Otoritesi

OpenCore stable release sürüm biçimi `YYYY.MM.RELEASE` olacaktır. İlk OpenCore sürümü `2026.08.1` olarak belirlenmiştir:

- `2026`: dört haneli release yılı,
- `08`: `01` ile `12` arasındaki iki haneli release ayı,
- `1`: o takvim ayı içinde yayımlanan OpenCore stable release sıra numarası.

Son bileşen takvim günü değildir. Sayaç her ay `1` ile başlar, aynı ay içindeki her yayımlanmış stable release için artar ve ay değiştiğinde yeniden `1` olur. Yayımlanmış bir sürüm kimliği immutable'dır ve farklı release içeriği için yeniden kullanılamaz.

Doğru sıralama örnekleri:

```text
2026.08.1 < 2026.08.2
2026.08.9 < 2026.08.10
2026.08.10 < 2026.09.1
2026.12.4 < 2027.01.1
```

Karşılaştırma naive lexical string comparison ile yapılmaz; yıl, ay ve release sıra numarası numeric bileşenler olarak karşılaştırılır. Uygulama bu sözleşmeyle uyumlu kanıtlanmış bir comparison mekanizması kullanabilir.

İlk updater yalnız stable sürümleri destekler. Gelecekte `2026.09.1-alpha.1`, `2026.09.1-beta.1` veya `2026.09.1-rc.1` benzeri pre-release suffix'leri ayrıca desteklenebilir; ilk uygulama buna bağlı olmayacaktır.

`system/version.php`, tek canonical OpenCore sürüm otoritesi olacaktır ve başlangıçta kavramsal olarak `VERSION = 2026.08.1` değerini sağlayacaktır. Root application entrypoint, Admin entrypoint, Upgrade UI, release discovery, manifest compatibility kontrolleri, installed-version raporlaması ve update state bu tek kaynağı kullanacaktır.

Mevcut `index.php` ve `ocadmin/index.php` içindeki bağımsız OpenCart-era `VERSION = 4.1.0.3` tanımları implementation phase'inde kaldırılır ve iki entrypoint `system/version.php` dosyasını yükler. Ayrı tutulan veya ayrı güncellenen ikinci bir version constant/config oluşturulmaz. OpenCart `4.1.0.3` yalnız tarihsel proje kökeni olarak belgelenebilir; OpenCore runtime sürümünün parçası değildir.

Release manifest sürümü, GitHub release tag'i ve staged `system/version.php` değeri aynı normalize edilmiş OpenCore sürümünü göstermelidir. Uyuşmazlık kurulumu durdurur. Installed version yalnız çalışan canonical kaynaktan okunur; DB setting veya package filename ikinci sürüm otoritesi olmaz.

## 5. Release Artifact Sözleşmesi

Artifact kökü aşağıdaki kapalı yapıyı kullanır:

```text
opencore-release/
  manifest.json
  payload/
    application/...
    vendor/...
```

`payload/application/` yalnız release tarafından sahiplenilen application dosyalarını, `payload/vendor/` ise ADR-002 ile uyumlu prebuilt Composer vendor payload'unu içerir. Paket `install/`, Marketplace/extension paketi, OCMOD veya bağımsız executable installer script içeremez.

`manifest.json` en az şunları taşır:

- `contract_version`,
- `application` (`opencore`),
- `version`,
- gerekirse `minimum_source_version` ve `maximum_source_version`,
- release kimliği ve build zamanı,
- her dosya için normalize relative path, byte size ve SHA-256,
- eklenen/değiştirilen dosya envanteri,
- açık ve exact kaldırılacak dosya listesi,
- protected-path policy sürümü,
- `composer_lock_sha256` ve vendor payload kimliği,
- DB değişikliği gerekip gerekmediği,
- varsa yalnız onaylı DB update contract kimlikleri,
- post-install doğrulama contract sürümü.

Manifest arşiv içindeki dosyaların tamamını kapsar. Manifestte olmayan payload, duplicate path, case-collision veya beklenmeyen top-level entry kurulumu reddettirir.

## 6. Doğrulama ve Trust Modeli

İndirme ve extraction öncesi/sonrası model şu kontrolleri yapar:

- kaynak repository ve release asset kimliği,
- desteklenen manifest şeması ve `application = opencore`,
- installed/source/target version uyumluluğu,
- external `.sha256` sidecar ile bütün artifact SHA-256 doğrulaması,
- manifestteki her payload dosyasının size ve SHA-256 doğrulaması,
- archive completeness ve exact inventory eşleşmesi,
- bütün archive ve manifest path'lerinin ownership karşılaştırması, protected-file kontrolü, extraction, staging, replacement veya removal kararından önce canonicalize ve normalize edilmesi,
- absolute path, `..`, drive-qualified/drive prefix, UNC path, staging root dışına çıkan path, malformed/ambiguous normalized path, symlink/junction ve path traversal reddi,
- Windows case-insensitive path collision kontrolü,
- protected path ve izin verilen root kontrolü,
- installer/extension/Marketplace/OCMOD payload reddi,
- staged `system/version.php` ve `composer.lock` kimliği kontrolü.

SHA-256 bütünlük doğrulaması, corruption ve trusted metadata'ya göre beklenmeyen artifact/dosya değişikliklerini tespit eder; yayıncı kimliğini tek başına kanıtlamaz. Artifact, checksum ve manifest aynı GitHub repository/account trust domain'inden geliyorsa bu domain'in compromise edilmesine karşı bağımsız koruma sağlamaz. Hash verification bağımsız release authenticity ile eşdeğer değildir. Cryptographic release signing veya bağımsız trust root daha sonra ayrı bir güvenlik/mimari kararla eklenebilir; ADR-004 uygulanmamış bir signing sistemi tanımlamaz.

## 7. Staging

Updater çalışma alanı web root dışında, conceptual olarak `DIR_STORAGE . 'updates/'` altında bulunur:

```text
updates/
  downloads/<update-id>/
  staging/<update-id>/
  backups/<update-id>/
  state/<update-id>.json
  locks/upgrade.lock
  journals/<update-id>.jsonl
  logs/<update-id>.log
```

Her update benzersiz, tahmin edilemez bir kimlik kullanır. Directory oluşturma, izinler ve cleanup implementation phase'inde yapılır. Download, staging, backup ve state dosyaları repository'ye yazılmaz; doğrudan web erişimine açılmaz. Başarısız update kanıtları kontrollü retention süresince korunabilir; credentials veya secrets loglanmaz.

## 8. Dosya Uygulama Politikası

Dosya güncellemesi yalnız doğrulanmış manifest envanterine göre yapılır:

- yeni dosyalar yalnız izin verilen release-owned path'lere eklenir,
- mevcut dosyalar değişim öncesi backup'a alınır ve temp dosyadan same-filesystem rename ile değiştirilir,
- dosya silme yalnız manifestin exact removal listesiyle yapılır,
- manifest dışı yerel dosyalar korunur,
- uygulanan her adım update journal/state'e yazılır,
- dosya izinleri mevcut güvenli izin policy'sine göre korunur veya allowlist ile uygulanır,
- kesinti sonrası state `in_progress`, `failed`, `rollback_required` veya `complete` olarak tekrar okunabilir.

Tüm arşivi canlı tree üzerine recursive copy etmek yasaktır. Uygun deployment düzeninde versioned release directory ve atomic pointer/swap tercih edilir. Mevcut layout bunu desteklemiyorsa temp-file + rename ve journal uygulanır; çok dosyalı filesystem değişiminin bütünü atomic kabul edilmez.

## 9. Protected Yerel Dosyalar

Aşağıdakiler payload tarafından overwrite veya delete edilemez:

- root `config.php`,
- `ocadmin/config.php`,
- `.env` ve `.env.*`,
- `.git/` ve repository metadata,
- external storage'ın updater tarafından yönetilmeyen tüm içeriği,
- runtime cache, log, session, download ve temporary upload alanları,
- kullanıcı/business tarafından yüklenmiş medya ve deployment-specific dosyalar,
- credentials, local secrets ve host-specific configuration.

Protected policy deny-first'tür ve karşılaştırmalar loose filename üzerinden değil canonical normalized path üzerinden yapılır. Release-owned dosya allowlist'i ile protected list çakışırsa update reddedilir; sessiz overwrite yapılmaz. Local config değişikliği gerekiyorsa ayrı, açıkça belgelenmiş administrator adımı gerekir.

## 10. Composer ve Vendor

ADR-002 geçerlidir. Production makinesinde Composer CLI varsayılmaz. Release build süreci `composer.lock` üzerinden production vendor payload'u üretir ve artifact içine koyar.

Updater:

- staged `composer.lock` SHA-256 değerini manifest ile karşılaştırır,
- vendor payload'un envanterini ve hashlerini doğrular,
- application ve vendor'u eşleşen tek release birimi olarak hazırlar,
- external `DIR_STORAGE/vendor/` için ayrı backup/versioned directory oluşturur,
- application başarıyla doğrulanmadan yeni vendor'u aktif etmez,
- başarısız aktivasyonda önceki vendor pointer/directory'sine döner.

Canlı vendor tree üzerine dependency bazında merge yapılmaz ve runtime `composer install/update` çalıştırılmaz.

## 11. Veritabanı Update Sınırı

DB dönüşümü gerektirmeyen release'ler native updater tarafından kurulabilir. `system/helper/db_schema.php` production update için çalıştırılmaz.

DB dönüşümü isteyen release, ayrıca onaylanmış ve versioned OpenCore DB update contract olmadan reddedilir. En küçük uyumlu mekanizma:

- model tarafından çağrılan, source-controlled ve exact source-version/target-version kimliğine bağlı update handler,
- manifestte yalnız handler kimliği ve beklenen pre/postcondition bilgisi,
- arbitrary SQL veya executable installer script içermeyen artifact,
- controller içinde SQL bulunmaması,
- handler allowlist'i ve contract-version kontrolü.

Her handler:

1. exact source ve target version'ı tanımlar,
2. exact installed version ile diğer DB precondition'larını read-only doğrular,
3. etkilenen exact table, column ve data kapsamını bildirir,
4. hedef tablolar/veriler için targeted backup alınmasını zorunlu kılar,
5. transformation adımlarını ve desteklenen transactional boundary'yi tanımlar,
6. affected-row ile schema/data postcondition ve validation kurallarını doğrular,
7. idempotency ve kesinti sonrası re-entry beklentisini açıkça tanımlar,
8. failure davranışını, reverse recovery desteğini ve recovery prosedürünü bildirir,
9. assertion sapmasında, MySQL tarafından gerçekten desteklendiği ölçüde rollback eder ve dosya aktivasyonunu durdurur.

DDL dahil her işlemin transaction ile geri alınabileceği varsayılmaz ve MySQL'in sağlamadığı güvence iddia edilmez. Arbitrary migration discovery, sırayla klasördeki SQL/PHP dosyalarını çalıştırma veya generic migration framework bu ADR tarafından onaylanmaz. Phase 1-4 updater DB değişikliği bulunan paketi reddeder. İlk DB handler contract'ı Phase 5'te ayrıca review ve onay alacaktır.

## 12. Backup ve Rollback

Mutasyon öncesi en az şunlar kaydedilir:

- installed version ve release manifest snapshot,
- değişecek/silinecek application dosyaları,
- mevcut external vendor release'i,
- dosya izin/kimlik metadata'sı,
- DB değişikliği varsa targeted ve doğrulanmış DB backup,
- update state/journal ve doğrulama sonuçları.

Dosya ve vendor rollback, DB mutasyonu başlamadan önce veya DB değişikliği olmayan release'lerde otomatik yapılabilir. Transaction içindeki DB işlemi assertion hatasında rollback edilir. MySQL commit edilmiş ve geri dönüşü yıkıcı/irreversible bir dönüşüm ile filesystem swap tek atomic transaction değildir; updater bunu atomic olarak sunmaz. Böyle bir failure `recovery_required` üretir, backup path/hash ve exact manuel recovery adımlarını gösterir.

Filesystem rollback DB rollback anlamına gelmez; DB rollback de filesystem rollback anlamına gelmez. DB-changing release'in recovery gereklilikleri Apply başlamadan önce bilinir ve doğrulanır. Otomatik rollback yalnız ilgili release contract'ının gerçekten garanti ettiği kapsam için sunulur.

Backup varlığı, boyutu, hash'i ve okunabilirliği apply öncesi doğrulanır. Backup doğrulanamazsa update başlamaz.

UI ve durable state en az `SUCCESS`, `FAILED_RECOVERABLE` ve `RECOVERY_REQUIRED` eşdeğeri sonuçları birbirinden ayırır.

## 13. Concurrency ve Update Lock

External storage altında tek upgrade lock ve durable state kullanılır. Lock en az owner/update ID, hedef sürüm, başlangıç zamanı, process/request identity ve heartbeat taşır.

- Aynı anda ikinci Check yapılabilir, ikinci Apply yapılamaz.
- Repeated Apply aynı update ID için idempotent state cevabı verir.
- Stale lock yalnız timeout'a bakılarak sessiz silinmez; process/state/journal incelenir ve modify izniyle explicit recovery gerekir.
- Her state geçişi atomic temp-write + rename ile yazılır.
- Request kesilirse sonraki istek resume/rollback/recovery durumunu gösterir; körlemesine yeniden başlamaz.

Apply fail-closed çalışır. Durable state çözülmemiş `applying`, `interrupted`, `recovery_required` veya `rollback_required` durumlarından birini gösteriyorsa yeni Apply başlamaz. Önce önceki update açıkça recover, rollback veya resolve edilmelidir. Stale-lock recovery yalnız elapsed time'a dayanamaz ve lock kaldırılmadan önce durable state ile operation journal doğrulanmalıdır.

Bu yapı yeni bir framework değil, updater modeline ait dar bir filesystem lock/state sözleşmesidir.

## 14. Request Güvenliği ve Updater Lock

Dosya aktivasyonu sırasında updater'a özgü bir runtime lock gerekir. Bu lock kaldırılmış storefront `config_maintenance` ayarını geri getirmez.

Admin ve API bootstrap, kısa kritik aktivasyon penceresinde update durumunu okuyarak güvenli `503 Service Unavailable` cevabı verebilir. Upgrade'i başlatan yetkili admin recovery/status endpoint'lerine erişebilmelidir. Lock yalnız kritik apply/rollback penceresinde tutulur; download ve staging sırasında gereksiz outage oluşturmaz.

## 15. UI ve Permission Modeli

`Maintenance -> Upgrade` korunur. UI durumları:

- Current version,
- Checking for updates,
- Up to date,
- New version available,
- Release notes ve compatibility bilgisi,
- Downloading/Validating,
- Ready to install,
- Backup hazırlanıyor,
- Installing/Validating installation,
- Success,
- Failure, rollback veya recovery required.

Sayfayı açmak update kurmaz. Download/validate ve özellikle Apply açık administrator action'ı ister. Destructive confirmation hedef sürüm, backup kapsamı ve DB değişikliği olup olmadığını gösterir.

`access:tool/upgrade` ekranı ve read-only release bilgisini görme iznidir. `modify:tool/upgrade` download, staging cleanup, apply, rollback ve recovery mutasyonları için zorunludur. Backend permission kontrolü UI görünürlüğünden bağımsız korunur.

## 16. Güvenlik Hususları

- Kullanıcı tarafından sağlanan URL, path, repository veya shell command kabul edilmez.
- Archive entry'leri extraction öncesi doğrulanır; symlink/junction uygulanmaz.
- Paket içeriği PHP tarafından include edilmeden önce bütünlük ve contract doğrulanır.
- Secrets, token'lar, GitHub credentials ve config içerikleri loglanmaz.
- Release notes HTML'i güvenli biçimde escape/sanitize edilir.
- Download size, extracted size, dosya sayısı ve süre limitleri uygulanır.
- Update state ve backup yalnız yetkili admin tarafından görüntülenir.
- CSRF token, POST ve modify permission tüm mutation endpoint'lerinde zorunludur.

## 17. Legacy Installer Cleanup Sınırı

Native updater uygulanıp runtime doğrulandıktan sonra ayrı cleanup phase'inde şunlar kaldırılabilir:

- OpenCart upgrade API entegrasyonu,
- OpenCart GitHub release entegrasyonu,
- `opencart-*` paket varsayımları,
- `upload/install/` extraction ve handoff,
- `install/index.php` ve `upgrade/upgrade_1` varsayımları,
- orphan `system/config/install.php`,
- common/security install-directory UI davranışı,
- root ve admin entrypoint'lerindeki missing installer redirect fallback'leri.

Bu ADR bu dosyaları veya kodları şimdi kaldırmaz.

## 18. Sonuçlar

### 18.1 Olumlu

- Upgrade üretim özelliği OpenCore sahipliğinde kalır.
- Artifact, application, vendor ve sürüm kimliği doğrulanabilir olur.
- Installer/Marketplace/extension mimarisi geri getirilmez.
- Staging, backup, lock ve journal ile kesinti riski görünür ve yönetilebilir olur.
- DB değişiklikleri genel migration yerine dar, review edilmiş contract'lara bağlanır.

### 18.2 Maliyet ve Sınırlar

- Build pipeline manifest, hash ve prebuilt vendor üretmelidir.
- External storage kapasitesi download, staging ve backup için yeterli olmalıdır.
- Çok dosyalı filesystem ile MySQL birlikte tam atomic değildir.
- Windows ve Linux path/permission davranışları ayrıca test edilmelidir.
- DB değişikliği gereken ilk release ayrı contract onayı olmadan kurulamaz.
- Release signing ilk sürümde zorunlu değildir; bu nedenle trust GitHub repository/account güvenliğine bağlıdır.

## 19. Uygulama Phase'leri

### Phase 1: ADR ve Sürüm Contract'ı

- ADR-004'ün proje sahibi tarafından Accepted yapılması.
- Canonical OpenCore `YYYY.MM.RELEASE` sözleşmesinin, manifest JSON Schema'nın, asset naming ve build contract'ının kesinleştirilmesi.
- Threat model ve test fixture'larının hazırlanması.

### Phase 2: Canonical Version Kaynağı

- `system/version.php` dosyasının `2026.08.1` ilk sürümüyle uygulanması.
- `index.php` ve `ocadmin/index.php` içindeki duplicate OpenCart-era version tanımlarının kaldırılması.
- Tüm runtime/update tüketicilerinin tek canonical version kaynağına bağlanması.

### Phase 3: OpenCore Discovery, Download, Validation ve Staging

- `tool/upgrade` controller'ın request/permission/UI orchestration'a daraltılması.
- Native `ocadmin/model/tool/upgrade.php` oluşturulması.
- `yaselgirgin/opencore` GitHub Releases discovery, artifact download, full validation ve external staging.
- UI state'leri ve access/modify enforcement.
- DB değişikliği isteyen release'lerin güvenli biçimde reddedilmesi.

### Phase 4: Kontrollü Apply ve Recovery

- Application file ve vendor backup.
- Manifest-driven add/replace/remove ve operation journal.
- Durable state, fail-closed update lock, updater-specific request lock ve interruption recovery.
- Desteklenen file/vendor rollback.
- Post-install PHP/runtime/API/admin doğrulama gate'leri.

### Phase 5: Açık DB Update Contract'ı

- Herhangi bir production DB-changing release desteklenmeden önce ayrı mimari/onay kararı.
- Versioned handler allowlist, pre/postcondition, targeted backup, transaction sınırı, idempotency ve re-entry testleri.
- Filesystem/DB atomicity sınırı ile release-specific manuel/reverse recovery prosedürleri.

### Phase 6: Legacy Cleanup

- OpenCart updater ve installer residue'nun dependency audit ile kaldırılması.
- `system/config/install.php`, missing-config install redirect fallback'leri, common/security install-directory UI ve installer handoff cleanup.
- OpenCart endpoint/package varsayımlarının kaldırılması.
- Production smoke, rollback rehearsal ve cleanup raporu.

Her phase ayrı, küçük ve geri alınabilir source change olarak review edilir. Bu `Proposed` ADR tek başına runtime veya DB mutasyonuna izin vermez.
