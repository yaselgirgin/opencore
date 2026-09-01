# ADR-006: OpenCore Kanonik Dağıtım, Kurulum ve Yaşam Döngüsü

- **Durum:** Accepted
- **Tarih:** 2026-08-26
- **Karar sahipleri:** Proje sahibi / teknik ekip
- **Hedef:** OpenCore

## 1. Bağlam

OpenCore, OpenCart'tan türetilmiş bir iç uygulama platformudur. Bir e-ticaret vitrini, extension platformu veya runtime paket yöneticisi olarak değil, eksiksiz bir uygulama olarak dağıtılır.

Önceki mimari, ayrı application ve vendor artifact'ları ile runtime native self-updater getirmişti. Bu yön geri alınmıştır. OpenCore; source control, geleneksel hosting ve shared-hosting ortamlarına uygun, daha yalın bir yaşam döngüsüne ihtiyaç duyar.

OpenCore'un veritabanı gelişimi yine kontrollü olmalıdır. Veritabanı gelişimi application/vendor deployment sürecinden ayrıdır ve gelecekte yalnız veritabanına yönelik `install/upgrade` tarafından yönetilecektir.

## 2. Karar

OpenCore Git repository'si kanonik ve dağıtılabilir uygulamanın kendisidir.

`main` branch'i ve stable release tag'leri eksiksiz, kurulabilir OpenCore ağaçlarını temsil eder. Stable bir tag için GitHub tarafından üretilen source archive standart indirilebilir pakettir ve ayrı bir OpenCore release builder olmadan doğrudan kurulabilir olmalıdır. Mimari yetki belirli bir GitHub arayüzüne değil, stable source tree/tag'e aittir.

Her resmi stable source archive, uygulama kodunu ve kanonik runtime vendor ağacını şu konumda içerir:

```text
system/storage/vendor/
```

OpenCore runtime kendi application veya vendor dosyalarını indirmez, stage etmez, değiştirmez ya da geri almaz.

Application ve vendor dosyaları operatör tarafından manuel olarak deploy edilir. Release veritabanı değişikliği gerektiriyorsa operatör daha sonra yalnız veritabanına yönelik `install/upgrade` uygulamasını çalıştırır.

Aşağıdaki ayrım bağlayıcıdır:

- **Runtime self-updater:** Deploy edilmiş application veya vendor dosyalarını indirir ya da değiştirir. Yasaktır.
- **Manuel application update:** Resmi stable source archive içindeki application ve vendor dosyalarının operatör tarafından deploy edilmesidir. Desteklenen application update yöntemidir.
- **`install/upgrade`:** Kurulu bir OpenCore veritabanına açık schema/data güncellemeleri uygular. Geçerlidir ve self-updater değildir.

`install/upgrade`, application veya vendor dosyalarını hiçbir zaman indiremez, stage edemez veya değiştiremez.

## 3. Mimari İlkeler

OpenCore doğrudan mimariyi korur:

```text
Controller -> Model -> Database
```

Repository yapısı; service, repository, ORM, dependency-injection, plugin-loader veya genel migration katmanları olmadan sahipliği anlaşılır kılmalıdır.

Yeni mimari katmanlar ayrı ve kabul edilmiş bir karar gerektirir.

Uygulama davranışı ile iş modülleri source control altında tutulur ve OpenCore'un parçası olarak teslim edilir. Runtime extension kurulumu ve runtime kod mutasyonu yasaktır.

## 4. Dağıtım Modeli

Git repository dağıtım kaynağıdır. `main` ve stable release tag'leri eksiksiz, kurulabilir release'ler içerir. Stable tag için üretilen GitHub Source ZIP standart indirilebilir pakettir ve doğrudan kurulum medyası olarak kullanılabilir.

Stable source tree şunları içerir:

- OpenCore application dosyaları
- installer dosyaları
- varsayılan storage yapısı
- `system/storage/vendor/` altındaki kanonik runtime vendor
- gerekli dokümantasyon ve yapılandırma şablonları

Şöyle ayrı bir pipeline yoktur:

```text
source repository
-> release builder
-> özel distribution ZIP
```

Bridge release, release manifest, ayrı vendor artifact veya runtime release payload kullanılmaz.

## 5. Repository ve Paket Modeli

Hedeflenen üst seviye ürün ağacı yaklaşık olarak şöyledir:

```text
catalog/
admin/
system/
install/
index.php
config-dist.php
.htaccess
robots.txt
README.md
LICENSE
```

`system/` ağacı, yeni kurulumun ihtiyaç duyduğu dağıtılabilir varsayılan storage ve runtime vendor içeriğini kapsar.

Nihai repository'de yalnız dağıtılan ürünün kurulması, çalışması, yönetilmesi veya belgelenmesi için gerekli dosyalar bulunur. Development-only release builder'lar, vendor deployer'lar, deployment geçmişleri, geçici migration varlıkları ve geçersiz mimari kayıtlar güncel ürün ağacına ait değildir.

## 6. Admin Yolu Modeli

Dağıtılan varsayılan Admin dizini:

```text
admin/
```

Kurulum sırasında OpenCore bu dizinin adını değiştirmeyi önerebilir. Yeniden adlandırma opsiyoneldir. Varsayılan `admin/` dizinini korumak desteklenir ve tekrarlayan runtime veya login uyarısı üretmez.

Application bootstrap, ikinci bir application config dosyasına ihtiyaç duymadan Admin'e özgü yolları türetmelidir.

## 7. Storage ve Vendor Modeli

Faz 9 kanonik lifecycle: External `DIR_STORAGE` kullanımında yeni release ile `system/storage/vendor/` yeniden mevcutsa bootstrap, Composer autoload'dan önce aktif `DIR_STORAGE/vendor/` dizinini tamamen release payload ile değiştirir. Merge/overlay veya eski vendor backup/staging yoktur. `rename()` cross-filesystem'de başarısızsa copy ve source-cleanup fallback'i kullanılır; yeni `vendor/autoload.php` doğrulanmadan bootstrap devam etmez. Başarıdan sonra internal `system/storage/` release ağacı kaldırılır. Bu işlem yalnız vendor hedefidir; `cache/`, `logs/`, `session/`, `upload/` ve `backup/` runtime/user dizinlerine dokunmaz. `install/upgrade` vendor'a dokunmaz.

Varsayılan storage dizini:

```text
system/storage/
```

Kanonik dağıtılmış vendor ağacı:

```text
system/storage/vendor/
```

Varsayılan internal storage kullanan kurulumda:

```text
DIR_STORAGE = system/storage/
```

Manuel update sırasında release dosyalarının değiştirilmesi, dağıtılmış yeni vendor ağacını da doğal olarak deploy eder.

Post-install Admin Security, tüm storage ağacını web root dışına taşımayı önerebilir; bu taşıma opsiyoneldir. Storage taşındığında vendor dahil gerekli tüm içerik tutarlı tek birim olarak taşınır ve aktif runtime vendor şu olur:

```text
DIR_STORAGE/vendor/
```

External storage kullanan bir kurulumda yeni release ile `system/storage/vendor/` yeniden mevcutsa, Bölüm 7'deki bootstrap preflight lifecycle aktif `DIR_STORAGE/vendor/` dizinini bu payload ile tamamen değiştirir. Manuel merge/senkronizasyon gerekmez.

FTP, hosting file manager veya eşdeğer manuel dosya aktarımı yeterlidir; shell tabanlı deployment aracı gerekmez.

Admin, release bildirimi ve `install/upgrade`; vendor senkronizasyonu, aktivasyonu, swap veya rollback yapamaz. Yalnız bootstrap preflight, tanımlanan release-owned vendor replacement işlemini uygular.

Mimari şunları kullanmaz:

- vendor activation pointer
- vendor swap
- runtime vendor deployer
- vendor release manifest
- otomatik vendor update
- vendor rollback engine

Varsayılan storage konumu desteklenir ve tekrarlayan uyarı üretmez.

## 8. Composer ve Son Kullanıcı Bağımlılık Politikası

OpenCore runtime vendor bağımlılıklarını uygulamayla birlikte dağıtır. Son kullanıcılar ve production hosting ortamları şunlara ihtiyaç duymaz:

- Composer CLI
- SSH
- shell erişimi
- `exec`
- `proc_open`
- runtime dependency resolution

Maintainer'lar dependency değişikliklerini hazırlamak ve incelemek için kontrollü development sürecinde Composer kullanabilir. Ortaya çıkan runtime vendor içeriği OpenCore ile birlikte commit edilir ve dağıtılır.

Dependency değişiklikleri kontrollü, incelenebilir ve dağıtılan uygulamayla uyumlu kalmalıdır. `system/storage/composer.json` dependency tanımı olarak kalabilir.

Hedef mimari şunları kullanmaz:

- ayrı dağıtım sözleşmesi olarak root Composer metadata
- untracked external vendor artifact
- runtime Composer çalıştırılması
- release-time vendor artifact builder
- production vendor activation aracı

Runtime ve Admin request'leri Composer bağımlılığı kuramaz veya güncelleyemez.

## 9. Tek Yapılandırma Modeli

OpenCore tek bir üretilmiş root config dosyası kullanır:

```text
config.php
```

Ayrı bir:

```text
admin/config.php
```

veya yeniden adlandırılmış eşdeğeri yoktur. Catalog, Admin ve Cron kendi bilinen runtime bağlamlarından application'a özgü yolları ve değerleri türetir. Installer yalnız root config üretir.

## 10. Installer Modeli

Fresh installer yalnız unconfigured (missing, empty veya partial root config) durumda çalışır; configured installation fresh reinstall yapamaz. Post-install Admin Security, `install/` dizini removal, opsiyonel Admin rename ve opsiyonel storage move sorumluluklarını yönetir.

OpenCore, stable OpenCart installer akışını kavramsal referans olarak kullanan ve azaltılmış OpenCore mimarisine uyarlanmış özel bir `install/` uygulaması sağlayacaktır.

Yeni kurulum şunlardan sorumludur:

- ortam gereksinimi kontrolleri
- veritabanı bağlantısı doğrulaması
- OpenCore schema oluşturulması
- gerekli seed data
- ilk administrator hesabı
- root `config.php` üretimi
- `database_version` başlangıç değeri
- opsiyonel Admin dizini önerisi
- opsiyonel external storage önerisi
- istendiğinde vendor dahil storage ağacının tutarlı biçimde taşınması

Installer kaldırılmış storefront, e-ticaret, Marketplace, extension veya modification mimarisini geri getiremez.

## 11. Yeni Kurulum Güvenliği

OpenCore zaten kuruluysa yeni kurulum fail-closed davranmalıdır. Karar, root config ve veritabanı durumu dahil güvenilir kurulum kanıtlarına dayanmalıdır. Fiziksel `install/` dizininin kalması yanlışlıkla overwrite veya yeniden kuruluma izin veremez.

`install/` dizininin silinmesi veya yeniden adlandırılması önerilebilir ancak zorunlu değildir. Dizinin varlığı tekrarlayan ilk-login uyarısı oluşturmaz.

## 12. Manuel Application Update Modeli

External storage kullanımında release vendor payload replacement'i Bölüm 7'deki bootstrap preflight lifecycle tarafından yapılır; operatör ayrı vendor merge/senkronizasyonu yapmaz.

Resmi application update akışı:

```text
Stable release bildirimi
-> operatör resmi stable source archive'i indirir
-> kurulumun ve veritabanının yedeğini alır
-> application dosyalarını manuel deploy eder
-> external storage etkinse bootstrap preflight release vendor payload'ını aktif vendor ile değiştirir
-> yalnız DB işi gerekiyorsa install/upgrade çalıştırır
```

Internal storage'da `system/storage/vendor/`, manuel release-file deployment ile güncellenir. External storage'da operatör release içindeki `system/storage/vendor/` içeriğini ayrıca aktif `DIR_STORAGE/vendor/` konumuna kopyalar.

Runtime bu application/vendor deployment işlemlerinin hiçbirini yapmaz.

Release belgeleri şunları açıkça belirtmelidir:

- korunan yerel yapılandırma
- kalıcı storage/data sınırları
- external-storage vendor senkronizasyon adımı
- `install/upgrade` gerekip gerekmediği

Süreç FTP, hosting file manager veya eşdeğer manuel dosya aktarımıyla uygulanabilir kalmalıdır.

## 13. Yalnız Veritabanına Yönelik `install/upgrade`

`install/upgrade` yalnız daha önce kurulmuş bir OpenCore veritabanında çalışır.

Şunları yapabilir:

- kurulu `database_version` değerini okumak
- gerekli source-controlled veritabanı adımlarını belirlemek
- açık schema/data update'lerini sırasıyla çalıştırmak
- postcondition'ları doğrulamak
- başarıdan sonra `database_version` değerini ilerletmek
- fail-closed davranıp uygulanabilir recovery bilgisi sunmak

Şunları yapamaz:

- release indirmek
- application dosyalarını değiştirmek
- vendor değiştirmek
- external vendor senkronize etmek
- application payload stage etmek
- filesystem manifest uygulamak
- application/vendor rollback yapmak
- runtime self-updater gibi davranmak

Veritabanı adımları açık ve source-controlled kalır. Bu karar genel migration framework, ORM, service veya repository katmanı getirmez.

## 14. VERSION ve database_version

`VERSION` application/release version'dır. `DATABASE_VERSION`, kodun gerektirdiği hedef database revision'dır ve current canonical baseline `1`'dir. Kurulu revision `oc_setting` içinde `code=system`, `key=database_version` olarak tutulur; pozitif ve monoton integer'dır (`1`, `2`, `3`, ...), `VERSION`'dan bağımsızdır. Fresh install migration zinciri çalıştırmaz; güncel `DATABASE_VERSION` değerini seed eder; mevcut baseline `1`'dir. Code-only release revision artırmaz; yayınlanmış revision adımları immutable'dır.

Kanonik application-code version yetkilisi:

```text
system/version.php
```

Kanonik kurulu veritabanı schema/data seviyesi:

```text
table : oc_setting
code  : system
key   : database_version
value : pozitif integer (1, 2, 3, ...)
```

Sorumlulukları ayrıdır:

- `system/version.php`, manuel deploy edilmiş application dosyalarının sürümünü tanımlar.
- `oc_setting`, `code=system`, `key=database_version`; yeni kurulum veya `install/upgrade` ile tamamlanmış veritabanı schema/data seviyesini tanımlar.

`database_version`; release download, staging, filesystem, lock, notification veya self-updater state değildir.

Diagnostics ve `install/upgrade`, uyumluluğu raporlamak ve gereken DB işini belirlemek için iki sürümü karşılaştırabilir. Bu karşılaştırma application/vendor self-update davranışı getiremez.

Revision `2` (`Upgrade2`), legacy `notification.status` sütunu mevcutsa tüm
`notification` satırlarını siler ve sonra sütunu kaldırır; `notification_target`
ile `notification_user` tablolarını oluşturur; ayrıca
`config_notification_expire_days=7` ayarını ve günlük bildirim temizleme cron
kaydını seed eder. Bu, açık ve source-controlled bir DB-only adımdır.

## 15. Backup ve Restore Sınırı

OpenCore yalın SQL backup/restore modeli kullanır:

- okunabilir SQL backup
- SQL restore
- yalnız güncel OpenCore veritabanı tabloları

Backup/Restore, application deployment ve database upgrade çalıştırılmasından bağımsızdır.

Structured JSON/NDJSON updater evidence, filesystem journal ve application/vendor rollback state kanonik backup mimarisinin parçası değildir. Application/vendor rollback operatörün backup ve deployment sorumluluğudur.

## 16. Yalnız Bildirim Amaçlı Release Kontrolü

Admin, en yeni stable OpenCore GitHub release'i için read-only kontrol yapabilir. Daha yeni stable version varsa release sayfasına bağlanan bilgilendirici notification oluşturulabilir.

Release kontrolü şunları yapamaz:

- release asset indirmek
- dosya stage etmek veya uygulamak
- vendor değiştirmek ya da senkronize etmek
- veritabanı değişikliği çalıştırmak
- recovery başlatmak

Prerelease'ler normal kullanıcıya bildirilmez. Tekrarlanan kontroller duplicate notification üretmemelidir.

### Notification Core

`is_global=1` olan bildirim tüm kullanıcılara yöneliktir. Global olmayan bir
bildirim en az bir `user` veya `user_group` hedefiyle `notification_target`
üzerinden yönlendirilir. Görünürlük sorgusu, `notification_user` kaydı yoksa
`COALESCE` ile `status=0` döndürür; `status=1` okunmuş, `status=2` dismiss
edilmiştir. Buna karşılık `unread_only`/badge filtresi yalnız
`notification_user` satırı olmayan bildirimleri (`nu.status IS NULL`) sayar.

Bildirim süre sonu, varsayılan değeri `7` gün olan
`config_notification_expire_days` ayarıyla hesaplanır. Günlük
`notification_cleanup` cron'u süresi dolmuş bildirimlerle onların
`notification_target` ve `notification_user` satırlarını siler.

Şema sözleşmesi: `notification_target` üzerinde
`UNIQUE(notification_id, target_type, target_id)` ve hedef arama indeksi;
`notification_user` üzerinde `PRIMARY KEY(notification_id, user_id)`,
`status`/`date_modified` alanları ve `user_status` indeksi bulunur.

## 17. System Diagnostics

Settings merkezi bir System Diagnostics alanı sağlar. Şunları raporlayabilir:

- kurulu OpenCore version
- en yeni stable version
- database version
- application/database uyumluluğu
- PHP ve database server sürümleri
- gerekli PHP extension'ları
- upload ve execution limitleri
- Admin, storage ve installer yolları
- gerekli runtime dizinlerinin yazılabilirliği

Durum seviyeleri:

- yeşil: sağlıklı
- turuncu: öneri
- kırmızı: karşılanmayan gerçek gereksinim veya operasyonel sorun

Varsayılan `admin/`, varsayılan `system/storage/` ve mevcut `install/` dizini otomatik olarak kırmızı değildir. Diagnostics gözlemsel ve tavsiye niteliğindedir; updater, vendor synchronizer veya deployment engine değildir.

## 18. Shared-Hosting Kısıtları

Yeni kurulum, runtime ve manuel update geleneksel shared hosting üzerinde çalışmalıdır. Production; Composer, shell erişimi, MySQL CLI, background deployment worker veya process-control fonksiyonu gerektiremez.

FTP, hosting file manager ve sıradan PHP web request'leri desteklenen kurulum/update akışları için yeterli olmalıdır. Opsiyonel maintainer veya CI tooling son kullanıcı runtime bağımlılığına dönüşemez.

## 19. Açıkça Reddedilen Mimari

Bu yasaklar `install/upgrade` veya Admin tarafından runtime vendor mutation'ını kapsar. Bölüm 7'de tanımlanan pre-autoload, release-owned external vendor replacement lifecycle'i istisnadır; runtime/user storage dizinlerini değiştirmez.

Şunlar reddedilmiştir:

- runtime application/vendor self-update
- runtime release download veya staging
- manifest kontrollü application değişimi
- `install/upgrade` veya Admin tarafından runtime vendor senkronizasyonu, değişimi veya swap
- updater filesystem journal, lock ve recovery state
- bridge release
- ayrı özel distribution ZIP
- product-repository release builder veya vendor deployer
- vendor activation pointer
- production Composer çalıştırılması
- runtime extension, Marketplace veya OCMOD kurulumu
- iki bağımsız application config dosyası
- structured updater-specific database backup evidence
- `config_maintenance` değerinin updater lock olarak kullanılması
- genel migration, service, repository veya ORM katmanları
- varsayılan Admin dizininin zorunlu yeniden adlandırılması
- varsayılan storage'ın zorunlu taşınması
- desteklenen varsayılan yollar için tekrarlayan uyarılar

## 20. Sonuçlar ve Ödünleşimler

### Olumlu sonuçlar

- Repository ile dağıtılan paket aynı kimliğe sahip olur.
- Stable tag'in source archive'i doğrudan kurulabilir.
- Runtime kendi application/vendor kodunu değiştiremez.
- Shared-hosting uyumluluğu iyileşir.
- Kurulum ve update sorumlulukları açıktır.
- Vendor bağımlılıkları Composer gerektirmeden sunulur.
- Veritabanı gelişiminin dar ve açık bir sınırı olur.
- Yapılandırma sahipliği basitleşir.
- Runtime saldırı ve hata yüzeyi küçülür.

### Ödünleşimler

- Runtime vendor doğrudan dağıtıldığı için repository büyür.
- Dependency update'leri incelenmiş vendor diff'leri oluşturur.
- Application ve external vendor update'leri operatör aktarımı gerektirir.
- External-storage kurulumu belgelenmiş ek vendor kopyası gerektirir.
- Application/vendor rollback operasyonel backup sorumluluğudur.
- Installer ve database-upgrade güvenliği dikkatle uygulanmalıdır.
- Geçiş ağacından hedefe ilerlemek koordineli repository, bootstrap, config ve installer değişiklikleri gerektirir.

## 21. Önceki Kararlarla İlişki

ADR-001; e-ticaret davranışının kaldırılması, API-only catalog yönü ve yalın native mimarinin korunması için temeldir. Geçiş ağacı ile tarihsel yürütme materyali dar biçimde düzeltilmiştir.

ADR-002'nin artifact üretim ve deployment mimarisi terk edilmiştir. Production'da Composer gerektirmeme, dependency değişikliklerini inceleme ve runtime dependency mutasyonunu yasaklama ilkeleri bu karara dahil edilmiştir. ADR-002 güncel ürün ağacından silinmiş, tarihsel kaydı Git geçmişinde kalmıştır.

ADR-003; runtime extension, Marketplace ve modification yasağı için yetkilidir. Dağıtım ve vendor yaşam döngüsünde ADR-006'ya tabidir.
