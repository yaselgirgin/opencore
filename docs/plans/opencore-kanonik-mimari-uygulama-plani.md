# OpenCore Kanonik Mimari Uygulama Planı

## Amaç

Bu plan ADR-006'yı uygular. Uygulama sıralamasının, bağımlılık audit'lerinin, geri alınabilir batch'lerin ve kabul kapılarının sahibidir. Mimari yetki ADR-006'da kalır; bu belge ADR değildir.

## Tamamlanmış Tarihsel Düzeltme

Aşağıdaki işler tamamlanmıştır ve gelecek faz değildir:

- Repository denetlenmiş `af55e66` baseline'ına geri alınmıştır.
- Zone listesi düzeltmesi yeniden uygulanmıştır.
- Uygulama filtrelerinde Enter ile submit davranışı yeniden uygulanmıştır.
- Terk edilmiş ADR-004 kaldırılmıştır.
- `af55e66` sonrasındaki native runtime self-updater uygulaması Git geçmişi düzeltmesiyle kaldırılmıştır.
- `update_gate`, updater startup gate ve baseline sonrası release builder dahil runtime updater'a özgü dosyalar artık yoktur.
- Eski uygulama `Maintenance -> Upgrade` arayüzü kaldırılmıştır.
- Runtime'daki tam `tool/upgrade` referansları kaldırılmıştır.
- Yalın SQL Backup/Restore davranışı rollback ile geri gelmiştir.
- Rollback sonrası yerel smoke testleri geçmiştir.

Yalın SQL Backup/Restore daha sonra E2E doğrulama gerektirir. Native updater'ı kaldırmak veya yalın SQL Backup/Restore'u geri getirmek için yeni faz oluşturulmayacaktır; bu düzeltmeler tamamlanmıştır.

## Terminoloji ve Sorumluluk Sınırları

### Runtime self-updater

Runtime self-updater; application release indirir veya stage eder, application/vendor dosyalarını değiştirir, application rollback/recovery yapar ya da updater lock/state tutar. Yasaktır ve kaldırılmıştır.

### Manuel application update

Desteklenen model:

```text
stable release bildirimi
-> operatör resmi stable source archive'i indirir
-> application dosyalarını manuel deploy eder
-> external storage etkinse bootstrap preflight release vendor payload'ını DIR_STORAGE/vendor/ ile değiştirir
-> DB işi gerekiyorsa install/upgrade çalıştırır
```

### `install/upgrade`

`install/upgrade`, daha önce kurulmuş OpenCore veritabanı için DB-only sistemdir. `database_version` değerini okur, açık source-controlled schema/data upgrade adımlarını çalıştırır ve `database_version` değerini ilerletir.

Application dosyalarını indirmez, stage etmez veya değiştirmez; vendor'ı değiştirmez ya da senkronize etmez; application rollback yapmaz.

## Faz 1 — Release / Build / Deployment / Updater Kalıntılarının Temizliği

Git rollback sonrasında kalmış, `af55e66` öncesine ait eski altyapıyı audit et ve kaldır.

Audit adayları:

- `system/build/`
- eski build/deploy tooling
- release/deployment sözleşmeleri
- updater/release terminolojisi
- eski runtime ve deployment kalıntıları

Her bağımlılığı kaldırmadan önce sınıflandır. Mevcut runtime'ın ihtiyaç duyduğu Composer/vendor tooling, Faz 2 kanonik karşılığını sağlamadan kaldırılmamalıdır.

Yalnız adında `upgrade` geçtiği için `install/`, `startup/upgrade` veya gelecekteki DB-upgrade kavramlarını self-updater kalıntısı sayma.

OpenCart `install/` referans dizini henüz eklenmemiştir ve bu fazın parçası değildir.

## Faz 2 — Dağıtım / Composer / Vendor Kanonik Mimarisi

Hedef durum:

- Repository eksiksiz dağıtım ağacıdır.
- Stable tag'in üretilmiş source archive'i doğrudan kurulabilir.
- Özel release builder yoktur.
- Vendor `system/storage/vendor/` altında tracked ve distributed olur.
- `system/storage/composer.json` kalabilir.
- Root Composer/build/deployer dağıtım sözleşmesi kaldırılır veya yeniden tasarlanır.
- Production ve son kullanıcı Composer, SSH veya shell erişimine ihtiyaç duymaz.

Geçiş tooling'i kaldırılmadan önce maintainer dependency workflow, incelenmiş vendor değişiklikleri, repository tracking kuralları ve runtime Composer bootstrap tanımlanıp doğrulanmalıdır.

## Faz 3 — Tek Root `config.php`

Hedef durum:

- Tek root `config.php`.
- Ayrı application config dosyası yok.
- App, API ve Cron bağlama özgü yolları bootstrap'tan türetir.
- Installer sonunda yalnız root config üretir.

`DIR_APPLICATION`, `DIR_CATALOG`, `DIR_STORAGE`, `HTTP_SERVER`, config include noktaları ve tekrarlanan application-specific değerler audit edilmelidir.

## Faz 4 — `app/` ve `api/` Uygulama Yolları

Repository'nin uygulama dizinlerini `app/` ve `api/` yap. Eski `/admin/` ve `/catalog/` yolları 404 döndürür; redirect veya deprecation alias ekleme.

## Faz 5 — Kanonik `system/storage/` Yapısı

Runtime vendor ve gerekli yazılabilir yapılar dahil `system/storage/` dizinini desteklenen varsayılan `DIR_STORAGE` olarak kur.

External storage desteğini koru. External taşıma zorunlu olmadan internal ve external `DIR_STORAGE` davranışını tanımla ve doğrula.

## Faz 6 — Yeni Kurulum Installer'ı

Orijinal OpenCart 4.1.0.3 `install/` uygulamasını manuel olarak referans/base şeklinde getir ve OpenCore'a uyarla. Stok installer davranışını körlemesine geri yükleme.

Installer içindeki şu bağımlılıkları audit et ve kaldır:

- e-ticaret schema ve seed data
- storefront varsayımları
- extension ve Marketplace altyapısı
- OCMOD
- çift config üretimi
- eski application path varsayımları
- eski OpenCart upgrade davranışı

Yeni kurulum; gereksinim kontrolleri, DB bağlantı doğrulaması, kanonik schema, gerekli seed data, ilk uygulama kullanıcısı, root `config.php` ve başlangıç `database_version` değerini sağlamalıdır.

Güncel yeni-veritabanı referansı 25 tablodur:

```text
address_format
country
country_description
cron
currency
event
language
length_class
length_class_description
location
notification
notification_target
notification_user
session
setting
upload
user
user_authorize
user_group
user_login
user_token
weight_class
weight_class_description
zone
zone_description
```

Bu liste uygulamaya karşı doğrulanacak referanstır; main veritabanını değiştirme yetkisi vermez.

## Faz 7 — Post-install Security: Install Removal ve Storage Taşıma — Tamamlandı

Bu işlemler installer adımı değil, post-install Security akışındadır; install directory removal aynı hardening kapsamındadır.

Storage davranışı:

- Varsayılan `system/storage/`.
- Installer external yol önerebilir.
- Storage taşıma opsiyoneldir.
- Kabul edilirse vendor dahil gerekli storage ağacının tamamı tutarlı biçimde taşınır.
- Varsayılan storage'ı korumak geçerlidir ve sonradan zorunlu uyarı üretmez.

## Faz 8 — Yeniden Kurulum Koruması ve `install/` Dizini Davranışı — Tamamlandı

Fresh/missing/empty/partial config installer davranışı ile configured-install fail-closed davranışı uygulanmıştır.

Şu kuralları uygula:

- Yeni kurulum mevcut OpenCore'u overwrite edemez.
- Fiziksel `install/` dizini kalabilir.
- Silme önerilebilir ama zorunlu değildir.
- Post-install Security install dizini removal modal'ı sağlar.
- Config yoksa ve installer mevcutsa yeni kurulum akışına girilebilir.
- Kurulu sistem `install/` fiziksel olarak kalsa da normal çalışır.

## Faz 9 — Yalnız Veritabanı için `install/upgrade` — Tamamlandı / Doğrulandı

Kanonik revision modeli `system/version.php` içindeki `DATABASE_VERSION` (baseline `1`) ile `oc_setting` altındaki pozitif, monoton integer `system/database_version` değeridir; `VERSION`'dan bağımsızdır. Fresh install migration çalıştırmaz; güncel `DATABASE_VERSION` değerini seed eder; mevcut baseline `1`'dir.

Tek controller/model DB-only upgrade zinciri, pending tüm `upgradeN()` methodlarını mutation öncesi Model Proxy-native `isset()` ile preflight eder. Revisionlar forward-only uygulanır; her başarılı revision sonrası marker ilerletilir. Missing method, invalid revision veya downgrade durumu fail-closed'dur. Upgrade için explicit backup confirmation ve action gerekir; otomatik backup, rollback, manifest veya ayrı auth/token sistemi yoktur.

Configured runtime guard: App DB `<` target durumunda mevcut `install/` ile upgrade ekranına yönlendirir; install yoksa fail-closed olur. API HTML redirect yerine HTTP 503 machine-readable error döner. DB `=` target normaldir; configured `/install/` blocked ekranı verir; DB `>` target ve invalid revision fail-closed'dur. Direct upgrade route, upgrade gerekmiyorsa bypass sağlamaz.

External storage'da release ile yeniden gelen `system/storage/vendor/`, Composer autoload öncesi bootstrap tarafından aktif external vendor ile tamamen değiştirilir. Bu DB migration değildir ve cache/logs/session/upload/backup dizinlerine dokunmaz.

Tam veritabanı version sözleşmesi:

```text
table : oc_setting
code  : system
key   : database_version
value : pozitif integer (1, 2, 3, ...)
```

Uygulanan davranış:

- Yalnız mevcut kurulum veritabanında çalışır.
- Açık, okunabilir, versioned schema/data adımları kullanır.
- Eksik adımları kronolojik çalıştırır.
- Her application release için boş migration zorunlu değildir.
- İlerlemeyi yalnız başarılı seviyelerden sonra kaydeder.
- Hedef `database_version` değerine yalnız tam başarıdan sonra ulaşır.
- Upgrade authorization modeli backup confirmation ve explicit action kullanır; ayrı application session/token mekanizması yoktur.
- Genel migration framework getirmez.
- Application/vendor dosyalarını hiçbir zaman indirmez veya değiştirmez.

Revision `2` (`Upgrade2`), legacy `notification.status` sütunu mevcutsa önce tüm
`notification` satırlarını siler, sonra bu sütunu kaldırır. Ardından
`notification_target` ve `notification_user` tablolarını oluşturur,
`config_notification_expire_days` ayarını varsayılan `7` ile ve günlük bildirim
temizleme cron kaydını seed eder.

Bildirim çekirdeğinde `is_global=1` tüm kullanıcılara görünür; global olmayan bir
bildirimin en az bir `user` veya `user_group` hedefi vardır. Görünürlük sorgusu,
`notification_user` kaydı yoksa `COALESCE` ile `status=0` döndürür; `status=1`
okunmuş, `status=2` dismiss edilmiştir. Buna karşılık `unread_only`/badge filtresi
yalnız `notification_user` satırı olmayan bildirimleri (`nu.status IS NULL`) sayar.
Süre sonu
`config_notification_expire_days` ile hesaplanır. Günlük
`notification_cleanup` cron'u süresi dolmuş bildirimleri ve onlara ait target/user
satırlarını siler.

Şema sözleşmesi: `notification_target` için
`UNIQUE(notification_id, target_type, target_id)` ve hedef arama indeksi;
`notification_user` için `PRIMARY KEY(notification_id, user_id)`,
`status`/`date_modified` alanları ve `user_status` indeksi.

Kod doğrulama referansları: `app/model/tool/notification.php`,
`system/helper/db_schema.php`,
`install/model/upgrade/upgrade.php` içindeki `upgrade2()`,
`api/controller/cron/notification_cleanup.php` ve
`api/model/tool/notification.php`.

## Faz 10 — Yalnız Bildirim Amaçlı Stable Release Kontrolü

Uygulama arayüzü en yeni stable OpenCore release'i kontrol edebilir. Sürüm `system/version.php` değerinden yeniyse duplicate olmayan informational notification oluşturur ve isteğe bağlı olarak release sayfasına link verir.

Kontrol; download, staging, application/vendor/DB mutation veya rollback/recovery yapmaz. Prerelease normal kullanıcılara bildirilmez.

## Faz 11 — Settings -> System Diagnostics

Merkezi, bilgilendirici ve tavsiye niteliğinde diagnostics alanı sağla.

OpenCore durumu:

- Kurulu Version
- En Yeni Stable Version
- Database Version
- Version/DB uyumluluğu

Ortam durumu:

- PHP version
- MariaDB/MySQL
- cURL
- OpenSSL
- ZIP
- GD/Imagick
- file uploads
- `memory_limit`
- `upload_max_filesize`
- `post_max_size`
- `max_execution_time`

Yol ve güvenlik durumu:

- uygulama dizini
- storage dizini
- install dizini
- storage yazılabilirliği
- cache yazılabilirliği
- logs yazılabilirliği
- uploads yazılabilirliği

Önem seviyeleri:

- yeşil: sağlıklı
- turuncu: öneri
- kırmızı: gerçek sorun

Varsayılan `/app/`, varsayılan `/system/storage/` ve mevcut `/install/` otomatik hata değildir. Diagnostics bir updater veya deployment engine'e dönüşemez.

## Faz 12 — README / Dokümantasyon / Tools Son Temizliği — Kısmen Tamamlandı

Kanonik mimari büyük ölçüde uygulandıktan sonra:

- README'yi sadeleştir
- güncel ürün ağacındaki eski cleanup/history belgelerini kaldır
- eski ADR ve runtime mimari kalıntılarını kaldır
- kullanılmayan `tools/` içeriğini kaldır
- eski development-only ürün ağacı içeriğini kaldır

Kök `README.md` eklenmiştir; OpenCore amacı, gereksinimler, kurulum, app/storage
seçenekleri, tek root config, SQL Backup/Restore, manuel application update,
external-storage vendor replacement lifecycle, DB-only `install/upgrade`,
bildirim/release denetimi, System Diagnostics ve lisansı belgeler.

`tools/` dizini mevcut değildir. `docs/cleanup/` altındaki tarihsel envanter ve audit
belgeleri silinmemiştir; tracked dosya silme için ayrı owner onayı gerekir.

Terk edilmiş self-updater mimarisini belgeleme.

## Faz 13 — Kanonik Dağıtım Ağacı Audit'i — Tamamlandı

Stable repository'nin kurulabilir ürünün kendisi olduğunu doğrula.

Yaklaşık hedef root:

```text
app/
api/
system/
install/
index.php
config-dist.php
.htaccess
robots.txt
README.md
LICENSE
```

Yalnız gerçekten gerekli ek runtime dosyalarına izin ver. Release builder veya distribution-artifact mekanizması kalmamalıdır.

ERT-21 completion kaydı: kanonik dağıtım ağacı audit'i tamamlandı. Owner kararıyla
`.htaccess.txt` canonical dağıtım dosyası olarak korunur; `.htaccess`e dönüştürülmez.
Root `cron.php`, `error.html`, `php.ini` ve `docs/cleanup/` tarihsel audit belgeleri
korunur. Bu istisnalar release builder veya distribution-artifact mekanizması değildir.

## Faz 14 — Tam E2E Doğrulama

Yalnız `C:\xampp\htdocs\opencore_test` ve test veritabanını kullan. Destructive veya E2E testlerde main OpenCore veritabanını hiçbir zaman değiştirme.

En az şunları doğrula:

- varsayılan yeni kurulum
- `app/` ve `api/` yolu ile yeni kurulum
- internal-storage kurulum
- external-storage kurulum
- yeniden kurulum koruması
- tek-root-config davranışı
- API ve app runtime
- SQL backup ve restore
- birden çok gerekli seviyeden geçen DB-upgrade zinciri
- yalnız bildirim amaçlı stable release kontrolü
- System Diagnostics
- stable source archive'den doğrudan kurulum
- external-storage release vendor replacement
- shared-hosting varsayımları

ERT-21 Faz 14 durumu: kısmi. İlk olarak `git archive HEAD` kaynak arşivi
`C:\xampp\htdocs\opencore_test\ert21-source` altında doğrudan kurulum için açıldı.
Bu arşivde `config.php` yokken boş tracked `config-dist.php` bulunması nedeniyle
fresh-install `step_2` denetiminde ilerleyemedi. Ardından single-root config
yazılabilirlik denetimi düzeltmeleri (`step_2.php` ve `step_3.php`) uygulandı ve test
çalışma kopyasına aktarıldı. Bu ikinci, güncellenmiş çalışma kopyasında varsayılan
internal-storage fresh install tamamlandı ve root `config.php` üretildi. App, API
ping, App login, System Diagnostics ve SQL Backup HTTP doğrulandı.

İlk SQL Restore denemesinde restore `oc_setting` tablosunu truncate ettikten sonraki
HTTP isteğinde runtime database-version guard `Database version could not be
determined` ile fail-closed oldu. Backup dosyasında `system/database_version` kaydı
bulunmasına rağmen guard, onu yeniden insert eden restore isteğine ulaşılmasını
engelledi. Bunun için App guard'a yalnız `tool/backup.restore` rotasıyla sınırlı
bypass eklendi; Backup Restore controller'ın permission ve filename doğrulamaları
değiştirilmedi. İzole `opencore_ert21` test veritabanı için restore zincirinin
yeniden çalıştırılması test-execution yetkisi tarafından bekletildiğinden bu düzeltme
henüz E2E ile doğrulanmadı. `C:\xampp\htdocs\opencore_test\ert21-source` çalışma
kopyası ve gerçek `opencore_ert21` veritabanında DB marker `2`den `3`e ilerletildi;
`install/upgrade` HTTP controller'a `backup=1&admin=admin` POST'u 200 JSON redirect
yanıtı verdi, marker `3` olarak kaldı ve `oc_release_notification` şeması doğrulandı.
Kod üzerinden mevcut `upgradeN()` preflight'ı ile her başarılı revision sonrasında
marker yazan sıralı mekanizma da doğrulandı. Owner kararıyla mevcut olmayan tarihsel
revisionlar için sentetik seed kullanılmadı; birden çok gerçek revision bulunduğunda
çok seviyeli E2E, gerçek zincir üzerinden ayrıca doğrulanacak. App/API route contract,
external storage, reinstall koruması, release kontrolü, external vendor
replacement ve shared-hosting senaryoları henüz doğrulanmadı.

## Çalışma Yöntemi

- Küçük ve bağımsız batch'ler kullan.
- İlgisiz refactor'lardan kaçın.
- Her fazdan önce güncel repository durumunu doğrula.
- Uygulama veya kaldırmadan önce bağımlılık audit'i yap.
- Upstream OpenCart'ı yalnız denetlenmiş referans olarak kullan; körlemesine backport etme.
- Runtime ile deployment/provisioning sorumluluklarını ayrı tut.
- External-storage kabiliyetini koru.
- Kanonik ADR açıkça değiştirmedikçe mevcut OpenCore davranışını koru.
- Her batch geri alınabilir olmalı.
- İlgili batch'lerden sonra syntax, static ve residue kontrolleri yap.
- Faz sınırlarında manuel smoke testleri çalıştır.
- Destructive DB testlerini yalnız `opencore_test` üzerinde yap.
- Tamamlanmamış mimariyi stable `main` branch'ine merge etme.

## Branch ve Release Politikası

- `develop`, aktif mimari ve development branch'idir.
- `main`, stable ve release branch'idir.

Güncel `main`, terk edilmiş önceki lineage'a aittir ve bu fazlar sırasında değiştirilmemelidir.

Yalnız kanonik uygulama ve tam E2E doğrulama tamamlandıktan sonra doğrulanmış `develop` lineage'ı, ayrıca onaylanmış bir Git işlemiyle stable `main` lineage'ı olabilir. Bu plan işlemin tam force/reset komutunu tanımlamaz.
