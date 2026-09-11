# ADR-003: OpenCore Kanonik Dağıtım, Kurulum ve Yaşam Döngüsü

- **Durum:** Accepted
- **Tarih:** 2026-08-26
- **Karar sahipleri:** Proje sahibi / teknik ekip
- **Hedef:** OpenCore

## 1. Bağlam

OpenCore eksiksiz bir uygulama olarak dağıtılır.

Bir runtime paket yöneticisi, extension platformu veya self-updating application değildir.

Application dosyalarının dağıtımı ile database schema/data gelişimi birbirinden ayrı sorumluluklardır.

Bu ADR; OpenCore'un dağıtım, kurulum, yapılandırma, storage/vendor ve database upgrade yaşam döngüsünün kalıcı sınırlarını tanımlar.

## 2. Kanonik Dağıtım Modeli

OpenCore Git repository'si dağıtılabilir uygulamanın kaynağıdır.

Stable release/tag, kurulabilir OpenCore source tree'sini temsil eder.

Ayrı bir zorunlu release builder veya özel distribution pipeline bulunmaz.

Dağıtılan source tree gerekli runtime dependency'lerini de içerir.

Kanonik runtime vendor konumu:

`system/storage/vendor/`

Son kullanıcı veya production ortamı OpenCore'u çalıştırmak için Composer kullanmak zorunda değildir.

## 3. Runtime Self-Update Yasağı

OpenCore runtime kendi application veya vendor dosyalarını:

- indirmez,
- stage etmez,
- değiştirmez,
- güncellemez,
- geri almaz.

Application ve vendor update operatör tarafından deployment yoluyla yapılır.

Runtime self-updater, release staging, filesystem rollback engine veya benzeri bir application deployment sistemi OpenCore mimarisinin parçası değildir.

## 4. Application Update ve Database Upgrade Ayrımı

Application update ve database upgrade ayrı işlemlerdir.

Application update, stable OpenCore source tree'nin operatör tarafından deploy edilmesidir.

Database değişikliği gerekiyorsa source-controlled database upgrade adımları ayrıca çalıştırılır.

`install/upgrade` yalnız database schema/data değişikliklerinden sorumludur.

`install/upgrade`:

- application dosyası indirmez veya değiştirmez,
- vendor indirmez veya değiştirmez,
- release stage etmez,
- application rollback yapmaz,
- self-updater gibi davranmaz.

## 5. HTTP Çalışma Modeli

OpenCore ürün ve runtime operasyonları CLI kullanımına bağımlı değildir.

Fresh install, database upgrade, cron ve benzeri operasyonel akışlar HTTP üzerinden çalışabilmelidir.

Bu karar development ve maintainer araçlarında CLI kullanımını yasaklamaz.

## 6. Uygulama Yapısı

Kanonik uygulama dizinleri:

- `app/`
- `api/`

Site kökü `/`, yönetim uygulamasını çalıştırır.

API sınırı `/api/` altında çalışır.

Legacy `admin/` ve `catalog/` application yapıları kanonik dağıtım modelinin parçası değildir.

## 7. Yapılandırma

OpenCore tek bir root yapılandırma dosyası kullanır:

`config.php`

Ayrı application config dosyaları oluşturulmaz.

App, API ve diğer runtime giriş noktaları gerekli application-specific değerleri mevcut runtime bağlamından türetir.

## 8. Storage ve Vendor

Varsayılan storage:

`system/storage/`

Runtime kodu storage erişiminde kanonik storage tanımını kullanır ve fiziksel storage konumunu gereksiz yere hard-code etmez.

Storage gerektiğinde web root dışına taşınabilir; bu zorunlu değildir.

External storage kullanıldığında dağıtılan release ile aktif runtime vendor ağacının tutarlı kalması gerekir.

Bu süreç runtime self-updater veya ayrı bir plugin/package sistemi oluşturamaz.

Cache, log, session, upload, backup ve benzeri kalıcı/runtime storage alanları application deployment ile gereksiz yere değiştirilmemelidir.

## 9. Composer ve Dependency Politikası

Runtime dependency'leri OpenCore ile birlikte dağıtılır.

Production veya son kullanıcı ortamı şunlara bağımlı değildir:

- Composer CLI
- SSH
- shell erişimi
- runtime dependency resolution

Maintainer'lar dependency değişiklikleri için development ortamında Composer kullanabilir.

Yeni veya güncellenmiş dependency'ler source control ve normal review sürecinden geçer.

Runtime request'leri dependency kuramaz veya güncelleyemez.

## 10. Fresh Install

Fresh install web tabanlıdır.

Installer yalnız henüz yapılandırılmamış bir OpenCore kurulumu üzerinde çalışmalıdır.

Yeni kurulum genel olarak şunlardan sorumludur:

- ortam gereksinimlerini doğrulamak,
- database bağlantısını doğrulamak,
- güncel schema ve gerekli seed data'yı oluşturmak,
- ilk administrator hesabını oluşturmak,
- root `config.php` üretmek,
- güncel database version seviyesini oluşturmak,
- gerekli storage yapılandırmasını tamamlamak.

Mevcut ve yapılandırılmış bir OpenCore installation yanlışlıkla fresh install ile overwrite edilememelidir.

## 11. Database Version ve Upgrade Zinciri

Application version ile database schema/data version birbirinden ayrıdır.

Application version, application release'ini tanımlar.

Kurulu database revision değeri:

```text
table : oc_setting
code  : system
key   : database_version
```

`database_version` pozitif ve monoton ilerleyen bir integer'dır.

Database değişikliği gerektiren release'lerde yeni source-controlled upgrade adımı eklenir.

Yayınlanmış upgrade adımları geriye dönük olarak değiştirilmemelidir.

Fresh install güncel schema/data seviyesini doğrudan oluşturabilir; mevcut installation ise gerekli upgrade adımlarını sırasıyla uygular.

Bu mekanizma genel amaçlı ORM veya migration framework oluşturmaz.

## 12. Backup ve Restore

OpenCore'un kanonik backup/restore modeli yalın SQL tabanlıdır.

Backup/Restore:

- application deployment'tan ayrıdır,
- database upgrade mekanizmasından ayrıdır,
- application/vendor rollback engine değildir.

Updater-specific manifest, filesystem journal veya recovery-state yapıları backup mimarisinin parçası değildir.

## 13. Release Kontrolü

OpenCore yeni stable release olup olmadığını read-only olarak kontrol edebilir ve kullanıcıyı bilgilendirebilir.

Release kontrolü:

- release indirmez,
- application/vendor değiştirmez,
- database upgrade çalıştırmaz,
- deployment veya recovery başlatmaz.

Bu özellik yalnız bilgilendirme amaçlıdır.

## 14. System Diagnostics

OpenCore merkezi bir diagnostics alanı sağlayabilir.

Diagnostics application, database, PHP/runtime gereksinimleri, storage ve temel sistem durumunu gözlemsel olarak raporlayabilir.

Diagnostics:

- updater değildir,
- deployment engine değildir,
- vendor synchronizer değildir,
- sistem durumunu kendiliğinden değiştirmez.

## 15. Shared Hosting Uyumluluğu

Fresh install ve normal runtime, geleneksel shared-hosting ortamında çalışabilmelidir.

Son kullanıcıdan zorunlu olarak:

- Composer,
- SSH,
- shell,
- process-control araçları,
- MySQL CLI

beklenmez.

Normal web request'leri ve standart dosya deployment yöntemleri desteklenen çalışma modelinin temelidir.

## 16. Diğer ADR'lerle İlişki

ADR-001, OpenCart e-ticaret katmanının kaldırılmasını ve korunan OpenCart çekirdek yaklaşımını tanımlar.

ADR-002, runtime extension, Marketplace ve OCMOD mimarisinin bulunmadığını ve application feature delivery sınırını tanımlar.

Bu ADR dağıtım, kurulum, yapılandırma, storage/vendor, application update ve database upgrade yaşam döngüsünden sorumludur.

## 17. Tarihsel Uygulama Kayıtları

Önceki updater denemeleri, release builder/artifact tasarımları, migration fazları, belirli database revision ayrıntıları, notification schema detayları ve implementation-specific lifecycle notları güncel mimari talimat değildir.

Bu tarihsel materyal `docs/history/` altında tutulur.
