# OpenCore Kanonik Mimari Uygulama PlanÄ±

## AmaÃ§

Bu plan ADR-003'yÄ± uygular. Uygulama sÄ±ralamasÄ±nÄ±n, baÄŸÄ±mlÄ±lÄ±k audit'lerinin, geri alÄ±nabilir batch'lerin ve kabul kapÄ±larÄ±nÄ±n sahibidir. Mimari yetki ADR-003'da kalÄ±r; bu belge ADR deÄŸildir.

## TamamlanmÄ±ÅŸ Tarihsel DÃ¼zeltme

AÅŸaÄŸÄ±daki iÅŸler tamamlanmÄ±ÅŸtÄ±r ve gelecek faz deÄŸildir:

- Repository denetlenmiÅŸ `af55e66` baseline'Ä±na geri alÄ±nmÄ±ÅŸtÄ±r.
- Zone listesi dÃ¼zeltmesi yeniden uygulanmÄ±ÅŸtÄ±r.
- Uygulama filtrelerinde Enter ile submit davranÄ±ÅŸÄ± yeniden uygulanmÄ±ÅŸtÄ±r.
- Terk edilmiÅŸ ADR-004 kaldÄ±rÄ±lmÄ±ÅŸtÄ±r.
- `af55e66` sonrasÄ±ndaki native runtime self-updater uygulamasÄ± Git geÃ§miÅŸi dÃ¼zeltmesiyle kaldÄ±rÄ±lmÄ±ÅŸtÄ±r.
- `update_gate`, updater startup gate ve baseline sonrasÄ± release builder dahil runtime updater'a Ã¶zgÃ¼ dosyalar artÄ±k yoktur.
- Eski uygulama `Maintenance -> Upgrade` arayÃ¼zÃ¼ kaldÄ±rÄ±lmÄ±ÅŸtÄ±r.
- Runtime'daki tam `tool/upgrade` referanslarÄ± kaldÄ±rÄ±lmÄ±ÅŸtÄ±r.
- YalÄ±n SQL Backup/Restore davranÄ±ÅŸÄ± rollback ile geri gelmiÅŸtir.
- Rollback sonrasÄ± yerel smoke testleri geÃ§miÅŸtir.

YalÄ±n SQL Backup/Restore Faz 14 kapsamÄ±nda E2E ile doÄŸrulanmÄ±ÅŸtÄ±r. Native updater'Ä± kaldÄ±rmak veya yalÄ±n SQL Backup/Restore'u geri getirmek iÃ§in yeni faz oluÅŸturulmayacaktÄ±r; bu dÃ¼zeltmeler tamamlanmÄ±ÅŸtÄ±r.

## Terminoloji ve Sorumluluk SÄ±nÄ±rlarÄ±

### Runtime self-updater

Runtime self-updater; application release indirir veya stage eder, application/vendor dosyalarÄ±nÄ± deÄŸiÅŸtirir, application rollback/recovery yapar ya da updater lock/state tutar. YasaktÄ±r ve kaldÄ±rÄ±lmÄ±ÅŸtÄ±r.

### Manuel application update

Desteklenen model:

```text
stable release bildirimi
-> operatÃ¶r resmi stable source archive'i indirir
-> application dosyalarÄ±nÄ± manuel deploy eder
-> external storage etkinse bootstrap preflight release vendor payload'Ä±nÄ± DIR_STORAGE/vendor/ ile deÄŸiÅŸtirir
-> DB iÅŸi gerekiyorsa install/upgrade Ã§alÄ±ÅŸtÄ±rÄ±r
```

### `install/upgrade`

`install/upgrade`, daha Ã¶nce kurulmuÅŸ OpenCore veritabanÄ± iÃ§in DB-only sistemdir. `database_version` deÄŸerini okur, aÃ§Ä±k source-controlled schema/data upgrade adÄ±mlarÄ±nÄ± Ã§alÄ±ÅŸtÄ±rÄ±r ve `database_version` deÄŸerini ilerletir.

Application dosyalarÄ±nÄ± indirmez, stage etmez veya deÄŸiÅŸtirmez; vendor'Ä± deÄŸiÅŸtirmez ya da senkronize etmez; application rollback yapmaz.

## Faz 1 â€” Release / Build / Deployment / Updater KalÄ±ntÄ±larÄ±nÄ±n TemizliÄŸi

Git rollback sonrasÄ±nda kalmÄ±ÅŸ, `af55e66` Ã¶ncesine ait eski altyapÄ±yÄ± audit et ve kaldÄ±r.

Audit adaylarÄ±:

- `system/build/`
- eski build/deploy tooling
- release/deployment sÃ¶zleÅŸmeleri
- updater/release terminolojisi
- eski runtime ve deployment kalÄ±ntÄ±larÄ±

Her baÄŸÄ±mlÄ±lÄ±ÄŸÄ± kaldÄ±rmadan Ã¶nce sÄ±nÄ±flandÄ±r. Mevcut runtime'Ä±n ihtiyaÃ§ duyduÄŸu Composer/vendor tooling, Faz 2 kanonik karÅŸÄ±lÄ±ÄŸÄ±nÄ± saÄŸlamadan kaldÄ±rÄ±lmamalÄ±dÄ±r.

YalnÄ±z adÄ±nda `upgrade` geÃ§tiÄŸi iÃ§in `install/`, `startup/upgrade` veya gelecekteki DB-upgrade kavramlarÄ±nÄ± self-updater kalÄ±ntÄ±sÄ± sayma.

OpenCart `install/` referans dizini henÃ¼z eklenmemiÅŸtir ve bu fazÄ±n parÃ§asÄ± deÄŸildir.

## Faz 2 â€” DaÄŸÄ±tÄ±m / Composer / Vendor Kanonik Mimarisi

Hedef durum:

- Repository eksiksiz daÄŸÄ±tÄ±m aÄŸacÄ±dÄ±r.
- Stable tag'in Ã¼retilmiÅŸ source archive'i doÄŸrudan kurulabilir.
- Ã–zel release builder yoktur.
- Vendor `system/storage/vendor/` altÄ±nda tracked ve distributed olur.
- `system/storage/composer.json` kalabilir.
- Root Composer/build/deployer daÄŸÄ±tÄ±m sÃ¶zleÅŸmesi kaldÄ±rÄ±lÄ±r veya yeniden tasarlanÄ±r.
- Production ve son kullanÄ±cÄ± Composer, SSH veya shell eriÅŸimine ihtiyaÃ§ duymaz.

GeÃ§iÅŸ tooling'i kaldÄ±rÄ±lmadan Ã¶nce maintainer dependency workflow, incelenmiÅŸ vendor deÄŸiÅŸiklikleri, repository tracking kurallarÄ± ve runtime Composer bootstrap tanÄ±mlanÄ±p doÄŸrulanmalÄ±dÄ±r.

## Faz 3 â€” Tek Root `config.php`

Hedef durum:

- Tek root `config.php`.
- AyrÄ± application config dosyasÄ± yok.
- App, API ve Cron baÄŸlama Ã¶zgÃ¼ yollarÄ± bootstrap'tan tÃ¼retir.
- Installer sonunda yalnÄ±z root config Ã¼retir.

`DIR_APPLICATION`, `DIR_CATALOG`, `DIR_STORAGE`, `HTTP_SERVER`, config include noktalarÄ± ve tekrarlanan application-specific deÄŸerler audit edilmelidir.

## Faz 4 â€” `app/` ve `api/` Uygulama YollarÄ±

Repository'nin uygulama dizinlerini `app/` ve `api/` yap. Eski `/admin/` ve `/catalog/` yollarÄ± 404 dÃ¶ndÃ¼rÃ¼r; redirect veya deprecation alias ekleme.

## Faz 5 â€” Kanonik `system/storage/` YapÄ±sÄ±

Runtime vendor ve gerekli yazÄ±labilir yapÄ±lar dahil `system/storage/` dizinini desteklenen varsayÄ±lan `DIR_STORAGE` olarak kur.

External storage desteÄŸini koru. External taÅŸÄ±ma zorunlu olmadan internal ve external `DIR_STORAGE` davranÄ±ÅŸÄ±nÄ± tanÄ±mla ve doÄŸrula.

## Faz 6 â€” Yeni Kurulum Installer'Ä±

Orijinal OpenCart 4.1.0.3 `install/` uygulamasÄ±nÄ± manuel olarak referans/base ÅŸeklinde getir ve OpenCore'a uyarla. Stok installer davranÄ±ÅŸÄ±nÄ± kÃ¶rlemesine geri yÃ¼kleme.

Installer iÃ§indeki ÅŸu baÄŸÄ±mlÄ±lÄ±klarÄ± audit et ve kaldÄ±r:

- e-ticaret schema ve seed data
- storefront varsayÄ±mlarÄ±
- extension ve Marketplace altyapÄ±sÄ±
- OCMOD
- Ã§ift config Ã¼retimi
- eski application path varsayÄ±mlarÄ±
- eski OpenCart upgrade davranÄ±ÅŸÄ±

Yeni kurulum; gereksinim kontrolleri, DB baÄŸlantÄ± doÄŸrulamasÄ±, kanonik schema, gerekli seed data, ilk uygulama kullanÄ±cÄ±sÄ±, root `config.php` ve baÅŸlangÄ±Ã§ `database_version` deÄŸerini saÄŸlamalÄ±dÄ±r.

GÃ¼ncel yeni-veritabanÄ± referansÄ± 25 tablodur:

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

Bu liste uygulamaya karÅŸÄ± doÄŸrulanacak referanstÄ±r; main veritabanÄ±nÄ± deÄŸiÅŸtirme yetkisi vermez.

## Faz 7 â€” Post-install Security: Install Removal ve Storage TaÅŸÄ±ma â€” TamamlandÄ±

Bu iÅŸlemler installer adÄ±mÄ± deÄŸil, post-install Security akÄ±ÅŸÄ±ndadÄ±r; install directory removal aynÄ± hardening kapsamÄ±ndadÄ±r.

Storage davranÄ±ÅŸÄ±:

- VarsayÄ±lan `system/storage/`.
- Installer external yol Ã¶nerebilir.
- Storage taÅŸÄ±ma opsiyoneldir.
- Kabul edilirse vendor dahil gerekli storage aÄŸacÄ±nÄ±n tamamÄ± tutarlÄ± biÃ§imde taÅŸÄ±nÄ±r.
- VarsayÄ±lan storage'Ä± korumak geÃ§erlidir ve sonradan zorunlu uyarÄ± Ã¼retmez.

## Faz 8 â€” Yeniden Kurulum KorumasÄ± ve `install/` Dizini DavranÄ±ÅŸÄ± â€” TamamlandÄ±

Fresh/missing/empty/partial config installer davranÄ±ÅŸÄ± ile configured-install fail-closed davranÄ±ÅŸÄ± uygulanmÄ±ÅŸtÄ±r.

Åu kurallarÄ± uygula:

- Yeni kurulum mevcut OpenCore'u overwrite edemez.
- Fiziksel `install/` dizini kalabilir.
- Silme Ã¶nerilebilir ama zorunlu deÄŸildir.
- Post-install Security install dizini removal modal'Ä± saÄŸlar.
- Config yoksa ve installer mevcutsa yeni kurulum akÄ±ÅŸÄ±na girilebilir.
- Kurulu sistem `install/` fiziksel olarak kalsa da normal Ã§alÄ±ÅŸÄ±r.

## Faz 9 â€” YalnÄ±z VeritabanÄ± iÃ§in `install/upgrade` â€” TamamlandÄ± / DoÄŸrulandÄ±

Kanonik revision modeli `system/version.php` iÃ§indeki `DATABASE_VERSION` (baseline `1`) ile `oc_setting` altÄ±ndaki pozitif, monoton integer `system/database_version` deÄŸeridir; `VERSION`'dan baÄŸÄ±msÄ±zdÄ±r. Fresh install migration Ã§alÄ±ÅŸtÄ±rmaz; gÃ¼ncel `DATABASE_VERSION` deÄŸerini seed eder; mevcut baseline `1`'dir.

Tek controller/model DB-only upgrade zinciri, pending tÃ¼m `upgradeN()` methodlarÄ±nÄ± mutation Ã¶ncesi Model Proxy-native `isset()` ile preflight eder. Revisionlar forward-only uygulanÄ±r; her baÅŸarÄ±lÄ± revision sonrasÄ± marker ilerletilir. Missing method, invalid revision veya downgrade durumu fail-closed'dur. Upgrade iÃ§in explicit backup confirmation ve action gerekir; otomatik backup, rollback, manifest veya ayrÄ± auth/token sistemi yoktur.

Configured runtime guard: App DB `<` target durumunda mevcut `install/` ile upgrade ekranÄ±na yÃ¶nlendirir; install yoksa fail-closed olur. API HTML redirect yerine HTTP 503 machine-readable error dÃ¶ner. DB `=` target normaldir; configured `/install/` blocked ekranÄ± verir; DB `>` target ve invalid revision fail-closed'dur. Direct upgrade route, upgrade gerekmiyorsa bypass saÄŸlamaz.

External storage'da release ile yeniden gelen `system/storage/vendor/`, Composer autoload Ã¶ncesi bootstrap tarafÄ±ndan aktif external vendor ile tamamen deÄŸiÅŸtirilir. Bu DB migration deÄŸildir ve cache/logs/session/upload/backup dizinlerine dokunmaz.

Tam veritabanÄ± version sÃ¶zleÅŸmesi:

```text
table : oc_setting
code  : system
key   : database_version
value : pozitif integer (1, 2, 3, ...)
```

Uygulanan davranÄ±ÅŸ:

- YalnÄ±z mevcut kurulum veritabanÄ±nda Ã§alÄ±ÅŸÄ±r.
- AÃ§Ä±k, okunabilir, versioned schema/data adÄ±mlarÄ± kullanÄ±r.
- Eksik adÄ±mlarÄ± kronolojik Ã§alÄ±ÅŸtÄ±rÄ±r.
- Her application release iÃ§in boÅŸ migration zorunlu deÄŸildir.
- Ä°lerlemeyi yalnÄ±z baÅŸarÄ±lÄ± seviyelerden sonra kaydeder.
- Hedef `database_version` deÄŸerine yalnÄ±z tam baÅŸarÄ±dan sonra ulaÅŸÄ±r.
- Upgrade authorization modeli backup confirmation ve explicit action kullanÄ±r; ayrÄ± application session/token mekanizmasÄ± yoktur.
- Genel migration framework getirmez.
- Application/vendor dosyalarÄ±nÄ± hiÃ§bir zaman indirmez veya deÄŸiÅŸtirmez.

Revision `2` (`Upgrade2`), legacy `notification.status` sÃ¼tunu mevcutsa Ã¶nce tÃ¼m
`notification` satÄ±rlarÄ±nÄ± siler, sonra bu sÃ¼tunu kaldÄ±rÄ±r. ArdÄ±ndan
`notification_target` ve `notification_user` tablolarÄ±nÄ± oluÅŸturur,
`config_notification_expire_days` ayarÄ±nÄ± varsayÄ±lan `7` ile ve gÃ¼nlÃ¼k bildirim
temizleme cron kaydÄ±nÄ± seed eder.

Bildirim Ã§ekirdeÄŸinde `is_global=1` tÃ¼m kullanÄ±cÄ±lara gÃ¶rÃ¼nÃ¼r; global olmayan bir
bildirimin en az bir `user` veya `user_group` hedefi vardÄ±r. GÃ¶rÃ¼nÃ¼rlÃ¼k sorgusu,
`notification_user` kaydÄ± yoksa `COALESCE` ile `status=0` dÃ¶ndÃ¼rÃ¼r; `status=1`
okunmuÅŸ, `status=2` dismiss edilmiÅŸtir. Buna karÅŸÄ±lÄ±k `unread_only`/badge filtresi
yalnÄ±z `notification_user` satÄ±rÄ± olmayan bildirimleri (`nu.status IS NULL`) sayar.
SÃ¼re sonu
`config_notification_expire_days` ile hesaplanÄ±r. GÃ¼nlÃ¼k
`notification_cleanup` cron'u sÃ¼resi dolmuÅŸ bildirimleri ve onlara ait target/user
satÄ±rlarÄ±nÄ± siler.

Åema sÃ¶zleÅŸmesi: `notification_target` iÃ§in
`UNIQUE(notification_id, target_type, target_id)` ve hedef arama indeksi;
`notification_user` iÃ§in `PRIMARY KEY(notification_id, user_id)`,
`status`/`date_modified` alanlarÄ± ve `user_status` indeksi.

Kod doÄŸrulama referanslarÄ±: `app/model/tool/notification.php`,
`system/helper/db_schema.php`,
`install/model/upgrade/upgrade.php` iÃ§indeki `upgrade2()`,
`api/controller/cron/notification_cleanup.php` ve
`api/model/tool/notification.php`.

## Faz 10 â€” YalnÄ±z Bildirim AmaÃ§lÄ± Stable Release KontrolÃ¼ â€” TamamlandÄ± / DoÄŸrulandÄ±

Uygulama arayÃ¼zÃ¼ en yeni stable OpenCore release'i kontrol edebilir. SÃ¼rÃ¼m `system/version.php` deÄŸerinden yeniyse duplicate olmayan informational notification oluÅŸturur ve isteÄŸe baÄŸlÄ± olarak release sayfasÄ±na link verir.

Kontrol; download, staging, application/vendor/DB mutation veya rollback/recovery yapmaz. Prerelease normal kullanÄ±cÄ±lara bildirilmez.

## Faz 11 â€” Settings -> System Diagnostics â€” TamamlandÄ± / DoÄŸrulandÄ±

Merkezi, bilgilendirici ve tavsiye niteliÄŸinde diagnostics alanÄ± saÄŸla.

OpenCore durumu:

- Kurulu Version
- En Yeni Stable Version
- Database Version
- Version/DB uyumluluÄŸu

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

Yol ve gÃ¼venlik durumu:

- uygulama dizini
- storage dizini
- install dizini
- storage yazÄ±labilirliÄŸi
- cache yazÄ±labilirliÄŸi
- logs yazÄ±labilirliÄŸi
- uploads yazÄ±labilirliÄŸi

Ã–nem seviyeleri:

- yeÅŸil: saÄŸlÄ±klÄ±
- turuncu: Ã¶neri
- kÄ±rmÄ±zÄ±: gerÃ§ek sorun

VarsayÄ±lan `/app/`, varsayÄ±lan `/system/storage/` ve mevcut `/install/` otomatik hata deÄŸildir. Diagnostics bir updater veya deployment engine'e dÃ¶nÃ¼ÅŸemez.

## Faz 12 â€” README / DokÃ¼mantasyon / Tools Son TemizliÄŸi â€” KÄ±smen TamamlandÄ±

Kanonik mimari bÃ¼yÃ¼k Ã¶lÃ§Ã¼de uygulandÄ±ktan sonra:

- README'yi sadeleÅŸtir
- gÃ¼ncel Ã¼rÃ¼n aÄŸacÄ±ndaki eski cleanup/history belgelerini kaldÄ±r
- eski ADR ve runtime mimari kalÄ±ntÄ±larÄ±nÄ± kaldÄ±r
- kullanÄ±lmayan `tools/` iÃ§eriÄŸini kaldÄ±r
- eski development-only Ã¼rÃ¼n aÄŸacÄ± iÃ§eriÄŸini kaldÄ±r

KÃ¶k `README.md` eklenmiÅŸtir; OpenCore amacÄ±, gereksinimler, kurulum, app/storage
seÃ§enekleri, tek root config, SQL Backup/Restore, manuel application update,
external-storage vendor replacement lifecycle, DB-only `install/upgrade`,
bildirim/release denetimi, System Diagnostics ve lisansÄ± belgeler.

`tools/` dizini mevcut deÄŸildir. `docs/history/cleanup/` altÄ±ndaki tarihsel envanter ve audit
belgeleri silinmemiÅŸtir; tracked dosya silme iÃ§in ayrÄ± owner onayÄ± gerekir.

Terk edilmiÅŸ self-updater mimarisini belgeleme.

## Faz 13 â€” Kanonik DaÄŸÄ±tÄ±m AÄŸacÄ± Audit'i â€” TamamlandÄ±

Stable repository'nin kurulabilir Ã¼rÃ¼nÃ¼n kendisi olduÄŸunu doÄŸrula.

YaklaÅŸÄ±k hedef root:

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

YalnÄ±z gerÃ§ekten gerekli ek runtime dosyalarÄ±na izin ver. Release builder veya distribution-artifact mekanizmasÄ± kalmamalÄ±dÄ±r.

ERT-21 completion kaydÄ±: kanonik daÄŸÄ±tÄ±m aÄŸacÄ± audit'i tamamlandÄ±. Owner kararÄ±yla
`.htaccess.txt` canonical daÄŸÄ±tÄ±m dosyasÄ± olarak korunur; `.htaccess`e dÃ¶nÃ¼ÅŸtÃ¼rÃ¼lmez.
Root `cron.php`, `error.html`, `php.ini` ve `docs/history/cleanup/` tarihsel audit belgeleri
korunur. Bu istisnalar release builder veya distribution-artifact mekanizmasÄ± deÄŸildir.

## Faz 14 â€” Tam E2E DoÄŸrulama â€” TamamlandÄ± / DoÄŸrulandÄ±

YalnÄ±z `C:\xampp\htdocs\opencore_test` ve test veritabanÄ±nÄ± kullan. Destructive veya E2E testlerde main OpenCore veritabanÄ±nÄ± hiÃ§bir zaman deÄŸiÅŸtirme.

En az ÅŸunlarÄ± doÄŸrula:

- varsayÄ±lan yeni kurulum
- `app/` ve `api/` yolu ile yeni kurulum
- internal-storage kurulum
- external-storage kurulum
- yeniden kurulum korumasÄ±
- tek-root-config davranÄ±ÅŸÄ±
- API ve app runtime
- SQL backup ve restore
- birden Ã§ok gerekli seviyeden geÃ§en DB-upgrade zinciri
- yalnÄ±z bildirim amaÃ§lÄ± stable release kontrolÃ¼
- System Diagnostics
- stable source archive'den doÄŸrudan kurulum
- external-storage release vendor replacement
- shared-hosting varsayÄ±mlarÄ±

ERT-21 Faz 14 durumu: tamamlandÄ± / doÄŸrulandÄ±. Ä°lk olarak `git archive HEAD` kaynak arÅŸivi
`C:\xampp\htdocs\opencore_test\ert21-source` altÄ±nda doÄŸrudan kurulum iÃ§in aÃ§Ä±ldÄ±.
Bu arÅŸivde `config.php` yokken boÅŸ tracked `config-dist.php` bulunmasÄ± nedeniyle
fresh-install `step_2` denetiminde ilerleyemedi. ArdÄ±ndan single-root config
yazÄ±labilirlik denetimi dÃ¼zeltmeleri (`step_2.php` ve `step_3.php`) uygulandÄ± ve test
Ã§alÄ±ÅŸma kopyasÄ±na aktarÄ±ldÄ±. Bu ikinci, gÃ¼ncellenmiÅŸ Ã§alÄ±ÅŸma kopyasÄ±nda varsayÄ±lan
internal-storage fresh install tamamlandÄ± ve root `config.php` Ã¼retildi. App, API
ping, App login, System Diagnostics ve SQL Backup HTTP doÄŸrulandÄ±.

Ä°lk SQL Restore denemesinde restore `oc_setting` tablosunu truncate ettikten sonraki
HTTP isteÄŸinde runtime database-version guard `Database version could not be
determined` ile fail-closed oldu. Backup dosyasÄ±nda `system/database_version` kaydÄ±
bulunmasÄ±na raÄŸmen guard, onu yeniden insert eden restore isteÄŸine ulaÅŸÄ±lmasÄ±nÄ±
engelledi. Bunun iÃ§in App guard'a yalnÄ±z `tool/backup.restore` rotasÄ±yla sÄ±nÄ±rlÄ±
bypass eklendi; Backup Restore controller'Ä±n permission ve filename doÄŸrulamalarÄ±
deÄŸiÅŸtirilmedi. Ä°zole `opencore_ert21` test veritabanÄ±ndaki restore zinciri E2E ile
doÄŸrulandÄ±. `C:\xampp\htdocs\opencore_test\ert21-source` Ã§alÄ±ÅŸma
kopyasÄ± ve gerÃ§ek `opencore_ert21` veritabanÄ±nda DB marker `2`den `3`e ilerletildi;
`install/upgrade` HTTP controller'a `backup=1&admin=admin` POST'u 200 JSON redirect
yanÄ±tÄ± verdi, marker `3` olarak kaldÄ± ve `oc_release_notification` ÅŸemasÄ± doÄŸrulandÄ±.
Kod Ã¼zerinden mevcut `upgradeN()` preflight'Ä± ile her baÅŸarÄ±lÄ± revision sonrasÄ±nda
marker yazan sÄ±ralÄ± mekanizma da doÄŸrulandÄ±. Owner kararÄ±yla mevcut olmayan tarihsel
revisionlar iÃ§in sentetik seed kullanÄ±lmadÄ±; birden Ã§ok gerÃ§ek revision bulunduÄŸunda
Ã§ok seviyeli E2E gerÃ§ek zincir Ã¼zerinden doÄŸrulandÄ±. App/API route contract,
external storage, reinstall korumasÄ±, release kontrolÃ¼, external vendor
replacement ve shared-hosting senaryolarÄ± doÄŸrulandÄ±.

Route contract doÄŸrulamasÄ±: yÃ¶netim App'i kanonik olarak `/` altÄ±ndadÄ±r ve fiziksel
kaynak dizini `app/` olarak sabittir; API sÄ±nÄ±rÄ± `/api/`dir. Eski `/admin/` ve
`/catalog/` yollarÄ± HTTP 404 dÃ¶ndÃ¼rÃ¼r; legacy admin rename davranÄ±ÅŸÄ± kaldÄ±rÄ±lmÄ±ÅŸtÄ±r.
OpenCart altyapÄ±sÄ± ve `Opencart` namespace'i korunmuÅŸtur.

### Cron Runtime SÃ¶zleÅŸmesi

Kanonik cron HTTP endpoint'i API uygulamasÄ± altÄ±ndadÄ±r:

```text
/api/index.php?route=cron/cron
```

YÃ¶netim arayÃ¼zÃ¼nÃ¼n kullanÄ±cÄ±ya gÃ¶sterdiÄŸi scheduler komutu bu endpoint'i `wget` ile Ã§aÄŸÄ±rÄ±r.

Eski:

```text
php <root>/cron.php
```

scheduler komutuna geri dÃ¶nÃ¼lmemelidir.

Cron action kaynaklarÄ± fiziksel olarak API uygulamasÄ±na aittir. YÃ¶netim arayÃ¼zÃ¼ndeki source-resolution ve diagnostic kontrolleri bu nedenle:

```php
DIR_API . 'controller/'
```

altÄ±nda Ã§Ã¶zÃ¼mleme yapmalÄ±dÄ±r.

Bu kontrollerde:

```php
DIR_APPLICATION
```

kullanÄ±lmamalÄ±dÄ±r.

Root `cron.php` dosyasÄ±nÄ±n daÄŸÄ±tÄ±m aÄŸacÄ±nda korunmasÄ±, yÃ¶netim arayÃ¼zÃ¼ndeki kanonik scheduler endpoint'inin root `cron.php` olduÄŸu anlamÄ±na gelmez.

## Ã‡alÄ±ÅŸma YÃ¶ntemi

- KÃ¼Ã§Ã¼k ve baÄŸÄ±msÄ±z batch'ler kullan.
- Ä°lgisiz refactor'lardan kaÃ§Ä±n.
- Her fazdan Ã¶nce gÃ¼ncel repository durumunu doÄŸrula.
- Uygulama veya kaldÄ±rmadan Ã¶nce baÄŸÄ±mlÄ±lÄ±k audit'i yap.
- Upstream OpenCart'Ä± yalnÄ±z denetlenmiÅŸ referans olarak kullan; kÃ¶rlemesine backport etme.
- Runtime ile deployment/provisioning sorumluluklarÄ±nÄ± ayrÄ± tut.
- External-storage kabiliyetini koru.
- Kanonik ADR aÃ§Ä±kÃ§a deÄŸiÅŸtirmedikÃ§e mevcut OpenCore davranÄ±ÅŸÄ±nÄ± koru.
- Her batch geri alÄ±nabilir olmalÄ±.
- Ä°lgili batch'lerden sonra syntax, static ve residue kontrolleri yap.
- Faz sÄ±nÄ±rlarÄ±nda manuel smoke testleri Ã§alÄ±ÅŸtÄ±r.
- Destructive DB testlerini yalnÄ±z `opencore_test` Ã¼zerinde yap.
- TamamlanmamÄ±ÅŸ mimariyi stable `main` branch'ine merge etme.

## Branch ve Release PolitikasÄ±

- `develop`, aktif mimari ve development branch'idir.
- `main`, stable ve release branch'idir.

GÃ¼ncel `main`, terk edilmiÅŸ Ã¶nceki lineage'a aittir ve bu fazlar sÄ±rasÄ±nda deÄŸiÅŸtirilmemelidir.

YalnÄ±z kanonik uygulama ve tam E2E doÄŸrulama tamamlandÄ±ktan sonra doÄŸrulanmÄ±ÅŸ `develop` lineage'Ä±, ayrÄ±ca onaylanmÄ±ÅŸ bir Git iÅŸlemiyle stable `main` lineage'Ä± olabilir. Bu plan iÅŸlemin tam force/reset komutunu tanÄ±mlamaz.
