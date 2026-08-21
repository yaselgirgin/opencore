# ADR-002: Composer and Vendor Dependency Management

- **Durum:** Accepted
- **Tarih:** 2026-08-21
- **Karar sahipleri:** Proje sahibi / teknik ekip
- **Hedef kod tabanı:** OpenCore
- **Tercih edilen model:** `ADOPT_COMPOSER_BUILD_ARTIFACT`

## 1. Bağlam

OpenCore repository kökünde `composer.json` ve `composer.lock` source-controlled dependency sözleşmesi olarak bulunur. Runtime bağımlılıkları Git tarafından izlenmeyen `DIR_STORAGE/vendor/` altında tutulur. Development veya production ortamına ait mutlak storage yolu taşınabilir dependency sözleşmesinin parçası değildir.

`system/framework.php` ve `cron.php`, üçüncü taraf sınıfları yalnız `DIR_STORAGE . 'vendor/autoload.php'` Composer bootstrap'ı üzerinden yükler. Artifact eksik veya geçersizse runtime alternatif bir manual bootstrap'a dönmez.

Marketplace installer shared root vendor alanına yazamaz veya bu alanı uninstall sırasında silemez. External vendor içindeki `composer/installed.json`, kurulu paketlerin anlık envanteridir; root dependency sözleşmesinin kaynağı `composer.json` ve `composer.lock` dosyalarıdır.

Mevcut production graph Twig ile gerekli Symfony deprecation/polyfill paketlerinden oluşur. Runtime SASS compilation ve scssphp ile kullanılmayan AWS/Guzzle graph'ı bağımsız cleanup batch'lerinde kaldırılmıştır.

OpenCore şu anda PHP 8.1 kullanmaktadır. PHP sürüm yükseltmesi bu kararın kapsamında değildir.

## 2. Karar

OpenCore, Composer dependency graph'ı için build-artifact modelini benimseyecektir:

1. Root dependency graph repository tarafından yönetilecektir.
2. `composer.json` ve `composer.lock` source-controlled dependency sözleşmesi olacaktır.
3. Vendor dosyaları repository'ye commit edilmeyecektir.
4. CI/build süreci production dependency'lerini lock dosyasından kurarak vendor payload'u üretecektir.
5. Production makinesinde Composer binary gerekmeyecektir.
6. Vendor payload, external-storage release artifact'ının parçası olacaktır.
7. Application kodu ve eşleşen vendor payload release orchestration tarafından birlikte seçilip deploy edilecektir.
8. Production vendor alanı mümkün olduğunca read-only olacaktır.
9. Runtime admin paneli dependency manager olarak kullanılmayacaktır.
10. Marketplace ve extension runtime işlemleri root `composer.json`, `composer.lock` veya shared root vendor graph'ını değiştiremeyecektir.
11. Extension-private dependency'ler extension scope içinde izole edilecek ve kendi autoload sınırını sağlayacaktır.
12. Runtime Composer autoload'a ayrı ve geri alınabilir bir migration phase'inde geçirilmiştir.
13. `system/vendor.php`, `system/helper/vendor.php` ve runtime vendor-generation davranışı Composer bootstrap doğrulandıktan sonra ayrı cleanup batch'lerinde kaldırılmıştır.

Her Composer, build, runtime veya deployment değişikliği ayrı, dar kapsamlı batch ve test gate'i ile uygulanacaktır.

## 3. External Storage Politikası

Vendor runtime'da `DIR_STORAGE/vendor/` altında kalır. `composer.lock` vendor konumundan bağımsızdır. Development ortamına özel Windows mutlak yolu `composer.json` içine yazılmayacaktır.

Build staging vendor konumu environment veya build configuration ile belirlenir. Development ve production storage yolları farklı olabilir; release packaging bu farkı external-storage payload aşamasında çözer. CI sağlayıcısı bu ADR'nin dışındadır; repository-owned artifact build ve activation araçlarının sözleşmesi Bölüm 7'de tanımlanır.

## 4. Sonuçlar

### 4.1 Olumlu sonuçlar

- Dependency çözümü lock tabanlı ve yeniden üretilebilir olur.
- Dependency provenance, review ve audit imkânı iyileşir.
- Application ve vendor için deterministic rollback sağlanır.
- Production ortamında Composer çalıştırılması gerekmez.
- Mevcut external storage yaklaşımı korunur.
- Runtime dependency mutation yüzeyi azalır.

### 4.2 Maliyetler ve olumsuz sonuçlar

- CI/build süreci daha karmaşık hâle gelir.
- Application commit'i, lock hash'i ve vendor artifact kimliği birlikte yönetilmelidir.
- Marketplace extension dependency politikası daha katı olur.
- Migration birden fazla bağımsız phase gerektirir.
- Dependency ownership runtime admin panelinden development ve build pipeline'a taşınır.

## 5. Değerlendirilen Alternatifler

### 5.1 `CURRENT_MANUAL_MODEL`

Runtime değişikliği gerektirmez ancak dependency resolution, provenance, audit ve rollback yeniden üretilebilir değildir. Nihai model olarak reddedilmiştir.

### 5.2 `MANIFEST_ONLY`

Manifest ve lock envanter ile audit'i iyileştirir. Buna karşın manuel autoload ile Composer graph arasında iki ayrı gerçeklik bırakır. Yalnız geçiş phase'i olarak kullanılabilir.

### 5.3 `COMPOSER_AUTOLOAD_WITH_EXTERNAL_VENDOR`

Production makinesinde Composer ve writable dependency ortamı gerektirir. Production dependency yönetimini artırdığı için nihai deployment modeli olarak reddedilmiştir.

### 5.4 `COMMIT_VENDOR`

Repository boyutunu ve dependency review gürültüsünü artırır; external-storage sözleşmesiyle uyumlu değildir. Reddedilmiştir.

### 5.5 Upstream committed-vendor modeli

OpenCart candidate hattındaki tracked vendor yaklaşımı OpenCore deployment modeli için gerekli değildir. OpenCore vendor'ı build artifact olarak yönetecektir.

## 6. Development Workflow

- Dependency ekleme ve güncelleme işlemleri yalnız development CLI üzerinden ve reviewed PR ile yapılacaktır.
- `composer.json` değişikliği ve kontrollü `composer.lock` refresh birlikte incelenecektir.
- Dependency değişiklikleri diğer runtime/refactor değişikliklerinden ayrı commit ve batch'lerde tutulacaktır.
- Runtime Developer ekranı dependency install veya update yapmayacaktır.
- Local vendor external storage veya disposable staging konumunda üretilebilir.
- Manifest/lock değişikliği olmadan shared root vendor içeriği elle değiştirilmeyecektir.

## 7. Build ve Deployment Workflow

Hedef CI/build süreci:

1. `composer validate --strict` çalıştırır.
2. Dependency'leri lock dosyasından `--no-dev` ve `--prefer-dist` ile kurar.
3. `composer audit` ve platform requirement kontrolü çalıştırır.
4. Application testlerini ve smoke testlerini çalıştırır.
5. Vendor inventory ve artifact hash bilgisi üretir.
6. Application ile external vendor payload'u aynı release paketi içinde ilişkilendirir.

Production Composer çalıştırmaz; prebuilt artifact deploy eder. Vendor mümkün olduğunca read-only olur. Application ve matching vendor artifact release orchestration tarafından birlikte seçilir ve rollback edilir. Exact CI sağlayıcısı bu ADR'nin dışındadır.

### 7.1 Build, activation ve runtime sınırı

- `build/build-vendor.php`, `composer.lock` üzerinden deterministic vendor artifact'i üretir.
- `build/deploy-vendor.php`, hazır artifact'i doğrular ve external storage altında aktive eder.
- Runtime yalnız `DIR_STORAGE . 'vendor/autoload.php'` sözleşmesini kullanır.
- Deployment aracı runtime `config.php` dosyalarını veya `DIR_STORAGE` sabitini yüklemez. Storage hedefi operator veya release orchestrator tarafından açıkça `--storage-dir=<path>` ile verilir. Böylece deployment yerel runtime config'ine veya ortama özel mutlak bir yola bağlanmaz.

### 7.2 Artifact identity ve trust boundary

Current application dependency compatibility, manifest içindeki `composer.json` ve `composer.lock` SHA-256 değerlerinin current release kaynaklarıyla eşleşmesiyle doğrulanır. Artifact içerik identity ve integrity'si, vendor inventory'sinin yeniden hesaplanan SHA-256 değeriyle doğrulanır.

Manifest'e Git commit veya `release_id` gömülmesi zorunlu değildir. Aynı `composer.json` ve `composer.lock` kullanan farklı application release'leri aynı doğrulanmış vendor artifact'ini kullanabilir. Application release ile artifact'in birlikte seçilmesi release orchestration sorumluluğudur.

Manifest ve inventory doğrulaması corruption/integrity ile dependency compatibility'yi doğrular; artifact authenticity veya cryptographic provenance kanıtlamaz. Artifact trusted build/release channel üzerinden sağlanmalıdır. Signing veya attestation gerekirse ayrı bir karar olacaktır.

### 7.3 Aktivasyon öncesi zorunlu kontroller

Deployment aracı aktivasyon veya kopyalama öncesinde aşağıdaki kontrolleri fail-closed uygulamalıdır:

- Tanınan ve yapısal olarak geçerli manifest schema
- Current release ile eşleşen Composer source hash'leri
- `vendor/autoload.php` varlığı
- Eksik veya fazla dosya bırakmayan tam inventory recomputation
- Dosya path, size, SHA-256, count, total bytes ve aggregate inventory parity
- Manifest, installed Composer metadata ve current lock production package graph parity
- Beklenmeyen development package bulunmaması
- Güvensiz veya iç içe path ilişkilerinin reddedilmesi
- Symlink, junction veya reparse-point escape riskinin fail-closed reddedilmesi

Repository içindeki artifact veya storage target reddedilir. Recursive delete yalnız current-run tarafından oluşturulan, canonical storage parent altındaki stage, backup veya failed path'leri için kullanılabilir. User-supplied arbitrary tree ve eski backup'lar otomatik silinmez.

### 7.4 Dry-run ve idempotency

`--dry-run`; manifest, source-hash compatibility, inventory, package graph ve path safety kontrollerini çalıştırır. Staging, rename, backup veya active vendor mutation yapmaz ve quiescence onayı gerektirmez.

Aynı source identity ve inventory zaten active vendor ile eşleşiyorsa araç `ALREADY_ACTIVE` başarılı no-op sonucu vermeli; gereksiz copy veya swap yapmamalıdır.

### 7.5 Quiescence ve aktivasyon sırası

Gerçek vendor aktivasyonu sırasında web ve cron traffic quiescent olmalıdır. Composer autoload dosyası request başında yüklense de class dosyaları request boyunca lazy-load edilebilir; çalışan request ile vendor tree değişiminin çakışması eski autoloader metadata'sını yeni veya eksik filesystem tree ile karıştırabilir.

Deployment aracı web server, PHP worker veya cron servislerini kendisi yönetmez. Operator veya release orchestrator gerekli süreçleri durdurmaktan sorumludur. Gerçek aktivasyon `--confirm-quiescent` onayı gerektirir.

Cross-platform aktivasyon sırası şöyledir:

1. Artifact doğrulanır.
2. Vendor tree storage-local benzersiz staging dizinine kopyalanır.
3. Stage inventory yeniden doğrulanır.
4. Mevcut vendor benzersiz backup sibling'e taşınır.
5. Stage, `vendor` hedefine taşınır.
6. Yeni `vendor/autoload.php` ile isolated post-activation smoke çalıştırılır.
7. İşlem success veya açık rollback sonucu ile tamamlanır.

Bu sequence “atomic deployment” değildir. Windows directory rename/open-file semantics nedeniyle bütün işlem atomik değildir. Linux'ta tek rename atomik olabilse de iki rename'li sequence bütün olarak atomik değildir. Bu nedenle quiescence zorunludur.

### 7.6 Rollback ve backup politikası

Mevcut vendor aktivasyon tamamlanana kadar korunur. İkinci rename veya post-activation smoke başarısız olursa backup'tan rollback denenir; rollback başarısı ve `ROLLBACK_FAILED` durumu ayrı raporlanır ve failure gizlenmez. Current-run dışındaki staging veya backup tree'leri otomatik temizlenmez.

Başarılı aktivasyon sonrasında current-run backup varsayılan olarak temizlenebilir; `--keep-backup` ile korunabilir. Backup cleanup failure aktivasyon failure değildir. Yeni vendor doğrulanmış ve active ise `SUCCESS_WITH_BACKUP_CLEANUP_WARNING` benzeri warning sonucu verilir; cleanup sorunu nedeniyle valid active vendor rollback edilmez.

Operational backup, application ve matching vendor artifact'in release orchestration tarafından birlikte rollback edilmesi sorumluluğunu ortadan kaldırmaz.

### 7.7 Production Composer yasağı

Deployment production ortamında Composer binary, `composer install`, `composer update` veya network dependency resolution çalıştırmaz. Production yalnız trusted release channel üzerinden gelen prebuilt artifact'i doğrular ve aktive eder.

## 8. Marketplace ve Extension Politikası

Marketplace extension'ları:

- Root `composer.json` dosyasını değiştiremez.
- Root `composer.lock` dosyasını değiştiremez.
- Shared root vendor graph'ına package ekleyemez veya buradan package silemez.
- Üçüncü taraf dependency gerekiyorsa extension-private scope kullanmalıdır.
- Kendi autoload sınırını sağlamalıdır.
- Root dependency compatibility gerekiyorsa install öncesi validation ileride ayrı olarak tasarlanabilir.

Mevcut Marketplace kodunun bu politikaya uyarlanması ayrı bir implementation görevidir.

## 9. Dependency Upgrade Politikası

Composer altyapı migration'ı ile dependency version upgrade ve pruning işlemleri ayrı batch'lerde tutulmuştur. Mevcut production graph Twig ile gerekli Symfony deprecation/polyfill paketlerinden oluşur; scssphp ve kullanılmayan AWS/Guzzle zinciri bağımsız cleanup batch'lerinde kaldırılmıştır.

Gelecekteki her dependency ekleme, upgrade veya kaldırma işlemi kendi compatibility analizi, testleri ve rollback sınırıyla uygulanacaktır.

## 10. PHP Politikası

Composer PHP constraint'i gerçekten desteklenen runtime ile uyumlu olacaktır. `config.platform.php` kullanılırsa dependency resolution hedef runtime'a sabitlenebilir; ancak gerçek runtime CI testi ve `check-platform-reqs` ile ayrıca doğrulanmalıdır.

PHP 8.2, 8.3 veya 8.4 migration kararı bu ADR'nin dışındadır.

## 11. Uygulanan Migration Stratejisi

Migration aşağıdaki bağımsız commit, review ve test gate'leriyle uygulanmıştır. Yeni değişiklikler aynı ayrık-batch disiplinini sürdürmelidir.

### Phase 0 — Architecture decision

Bu ADR ile build-artifact mimarisi kabul edilmiştir.

### Phase 1 — Mevcut dependency graph'ın tanımlanması

`composer.json`, doğrudan dependency'leri ve desteklenen sürüm sınırlarını tanımlar.

### Phase 2 — Lock ve vendor parity

`composer.lock` oluşturulmuş; lock'tan üretilen vendor ile external vendor arasında package, sürüm, autoload ve runtime parity doğrulanmıştır.

### Phase 3 — Opt-in development/build bootstrap

Composer install yalnız development ve disposable build staging ortamlarında çalışır.

### Phase 4 — Runtime Composer autoload

Framework ve cron, `DIR_STORAGE/vendor/autoload.php` Composer autoload'a geçirilmiştir.

### Phase 5 — Manuel bootstrap cleanup

Composer runtime doğrulandıktan sonra manual bootstrap ve runtime vendor-generation davranışı ayrı batch'lerde kaldırılmıştır.

### Phase 6 — Dependency upgrade ve pruning

Twig upgrade'i ile scssphp ve doğrulanmış unused AWS/Guzzle dependency cleanup'ları ayrı batch'lerde tamamlanmıştır.

## 12. Rollback Stratejisi

Her release application kimliğini, `composer.json`/`composer.lock` source identity'sini ve vendor inventory identity'sini ilişkilendirmelidir. Rollback application ve eşleşen vendor artifact'i birlikte restore etmelidir.

Vendor artifact identity doğrudan Git commit SHA'ya bağlı değildir. Dependency graph değişmeyen application release'leri aynı verified artifact'i yeniden kullanabilir; doğru application/artifact çiftinin seçimi release orchestration sorumluluğudur.

## 13. Güvenlik Değerlendirmeleri

Aşağıdaki kontroller supply-chain riskini azaltır ancak tamamen ortadan kaldırdığını garanti etmez:

- `composer.lock` tabanlı deterministic install
- Reviewed dependency değişiklikleri
- `composer audit`
- Artifact inventory ve checksum
- Production vendor'ın read-only olması
- Runtime dependency mutation yasağı
- Root dependency graph'ın açık sahipliği
- Application ve vendor release parity doğrulaması

## 14. Kapsam Dışı Konular

Bu ADR aşağıdaki gelecek kararları veya implementation ayrıntılarını onaylamaz:

- Artifact signing veya external attestation mekanizması
- Exact CI/release orchestration sağlayıcısı
- Frontend SCSS build pipeline'ı
- Extension-private dependency isolation tasarımı
- PHP upgrade'i

## 15. Kabul ve Uygulama Koşulu

Bu ADR `Accepted` durumundadır. Dependency graph, build, deployment veya runtime sözleşmesindeki sonraki değişiklikler ayrı review, test ve rollback sınırıyla uygulanmalıdır.
