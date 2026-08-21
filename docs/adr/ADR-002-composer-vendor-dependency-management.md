# ADR-002: Composer and Vendor Dependency Management

- **Durum:** Proposed
- **Tarih:** 2026-08-21
- **Karar sahipleri:** Proje sahibi / teknik ekip
- **Hedef kod tabanı:** OpenCore
- **Tercih edilen model:** `ADOPT_COMPOSER_BUILD_ARTIFACT`

## 1. Bağlam

OpenCore repository kökünde `composer.json` veya `composer.lock` bulunmamaktadır. Runtime bağımlılıkları Git tarafından izlenmeyen `DIR_STORAGE/vendor/` altında tutulur. Development ortamındaki örnek fiziksel konum `C:/xampp/storage_opencore/vendor/` olsa da bu mutlak yol taşınabilir dependency sözleşmesinin parçası değildir.

`system/framework.php` ve `cron.php`, üçüncü taraf sınıfları `system/vendor.php` üzerinden yükler. Bu dosya package autoload kayıtlarını OpenCart autoloader'a manuel olarak tanıtır. `system/helper/vendor.php`, mevcut vendor paketlerinin `composer.json` dosyalarını tarayarak `system/vendor.php` üretir. Admin Developer ekranındaki Vendor işlemi de aynı işi yapar; dependency indirmez, kurmaz veya güncellemez.

Marketplace installer, extension paketlerindeki `system/storage/` içeriğini shared storage alanına yazabilir ve vendor autoload kayıtlarının yeniden üretilmesini tetikleyebilir. External vendor içindeki `composer/installed.json`, kurulu paketlerin anlık envanterini gösterir ancak yeniden üretilebilir bir root dependency sözleşmesi değildir.

Mevcut paketler arasında Twig 3.18, scssphp 1.13 ve AWS SDK/Guzzle zinciri bulunmaktadır. Bunlar bu ADR ile güncellenmeyecek veya kaldırılmayacaktır. Root manifest ve lock bulunmadığı için aynı application commit'i farklı vendor içeriğiyle çalışabilir; application ve dependency rollback'i atomik değildir.

OpenCore şu anda PHP 8.1 kullanmaktadır. PHP sürüm yükseltmesi bu kararın kapsamında değildir.

## 2. Karar

OpenCore, Composer dependency graph'ı için build-artifact modelini benimseyecektir:

1. Root dependency graph repository tarafından yönetilecektir.
2. `composer.json` ve `composer.lock` source-controlled dependency sözleşmesi olacaktır.
3. Vendor dosyaları repository'ye commit edilmeyecektir.
4. CI/build süreci production dependency'lerini lock dosyasından kurarak vendor payload'u üretecektir.
5. Production makinesinde Composer binary gerekmeyecektir.
6. Vendor payload, external-storage release artifact'ının parçası olacaktır.
7. Application kodu ve eşleşen vendor payload aynı release kimliğiyle versionlanıp birlikte deploy edilecektir.
8. Production vendor alanı mümkün olduğunca read-only olacaktır.
9. Runtime admin paneli dependency manager olarak kullanılmayacaktır.
10. Marketplace ve extension runtime işlemleri root `composer.json`, `composer.lock` veya shared root vendor graph'ını değiştiremeyecektir.
11. Extension-private dependency'ler extension scope içinde izole edilecek ve kendi autoload sınırını sağlayacaktır.
12. Runtime'ın Composer autoload'a geçirilmesi ayrı bir migration phase'i olacaktır.
13. `system/vendor.php`, `system/helper/vendor.php` ve runtime vendor-generation davranışı ancak Composer bootstrap doğrulandıktan sonra kaldırılacaktır.

Bu ADR, Composer migration implementation'ını onaylamaz. Her migration phase'i ayrıca incelenecek, uygulanacak ve test edilecektir.

## 3. External Storage Politikası

Vendor, `DIR_STORAGE/vendor/` altında kalabilir. `composer.lock` vendor konumundan bağımsızdır. Development ortamına özel Windows mutlak yolu `composer.json` içine yazılmayacaktır.

Build staging vendor konumu environment veya build configuration ile belirlenecektir. Gerektiğinde `COMPOSER_VENDOR_DIR` benzeri build-time mekanizmalar kullanılabilir. Development ve production storage yolları farklı olabilir; release packaging bu farkı external-storage payload aşamasında çözmelidir. Bu ADR exact deployment script'i veya CI sağlayıcısı seçmez.

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

Production Composer çalıştırmaz; prebuilt artifact deploy eder. Vendor mümkün olduğunca read-only olur. Application ve vendor aynı release kimliğine bağlıdır. Exact CI sağlayıcısı ve deployment aracı bu ADR'nin dışındadır.

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

Composer altyapı migration'ı dependency version upgrade'i içermeyecektir. Önce mevcut dependency graph ve mevcut sürümler yeniden üretilecektir.

- Twig 3.18 → 3.24 ayrı minor-upgrade batch'i olacaktır.
- scssphp 1.13 → 2.1 ayrı major-upgrade batch'i olacaktır.
- AWS SDK/Guzzle zincirinin kaldırılması ayrı dependency-pruning batch'i olacaktır.
- League URI yalnız doğrulanmış dependency graph gerektiriyorsa eklenecektir.

Her upgrade kendi compatibility analizi, testleri ve rollback sınırıyla uygulanacaktır.

## 10. PHP Politikası

Composer PHP constraint'i gerçekten desteklenen runtime ile uyumlu olacaktır. `config.platform.php` kullanılırsa dependency resolution hedef runtime'a sabitlenebilir; ancak gerçek runtime CI testi ve `check-platform-reqs` ile ayrıca doğrulanmalıdır.

PHP 8.2, 8.3 veya 8.4 migration kararı bu ADR'nin dışındadır.

## 11. Migration Stratejisi

Her phase ayrı commit, review ve test gate'ine sahip olacaktır.

### Phase 0 — Architecture decision

Bu ADR review edilir ve ayrıca kabul edilirse sonraki phase'lere geçilir.

### Phase 1 — Mevcut dependency graph'ın tanımlanması

`composer.json`, mevcut doğrudan dependency'leri ve mevcut sürüm sınırlarını yeniden tanımlar. Runtime davranışı değişmez.

### Phase 2 — Lock ve vendor parity

`composer.lock` oluşturulur. Lock'tan üretilen vendor ile mevcut external vendor arasında package, sürüm, autoload ve runtime parity doğrulanır. Runtime hâlâ manuel bootstrap kullanır.

### Phase 3 — Opt-in development/build bootstrap

Composer install yalnız development ve disposable build staging ortamlarında etkinleştirilir. Production bootstrap değişmez.

### Phase 4 — Runtime Composer autoload

Framework ve cron, `DIR_STORAGE/vendor/autoload.php` Composer autoload'a tek, geri alınabilir bir batch içinde geçirilir.

### Phase 5 — Manuel bootstrap cleanup

Composer runtime doğrulandıktan sonra `system/vendor.php`, `system/helper/vendor.php` ve Developer/Marketplace vendor-generation davranışı ayrı batch'te kaldırılır.

### Phase 6 — Dependency upgrade ve pruning

Twig, scssphp ve doğrulanmış unused AWS/Guzzle dependency'leri ayrı batch'lerde güncellenir veya kaldırılır.

## 12. Rollback Stratejisi

Phase 1–3 runtime davranışını değiştirmemelidir. Runtime bootstrap migration'ı tek ve geri alınabilir batch olacaktır. Manuel bootstrap, Composer runtime doğrulanana kadar rollback noktası olarak korunacaktır.

Her release application commit'i, `composer.lock` hash'i ve vendor artifact kimliği taşımalıdır. Rollback application ve eşleşen vendor artifact'ı birlikte restore etmelidir.

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

Bu ADR şu anda aşağıdakileri yapmaz veya onaylamaz:

- Composer migration implementation'ı
- Package install, update veya vendor rebuild
- Runtime bootstrap değişikliği
- Twig veya scssphp upgrade'i
- AWS/Guzzle kaldırılması
- PHP upgrade'i
- Marketplace refactor'u
- `system/vendor.php` veya helper/generator cleanup'ı

## 15. Kabul ve Uygulama Koşulu

Bu ADR `Proposed` durumundadır. Review edilip ayrıca `Accepted` olarak işaretlenmeden Composer manifest, lock, build pipeline veya runtime bootstrap migration'ı başlatılmamalıdır.
