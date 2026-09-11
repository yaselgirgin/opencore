# ADR-003 Tarihsel Uygulama Kaydı — Dağıtım, Kurulum ve Yaşam Döngüsü

> **Durum:** Historical
>
> Bu dosya ADR-003'ün oluşumu ve uygulanması sırasında kullanılan eski implementation
> ayrıntılarını ve terk edilmiş mimari yaklaşımları saklar. Güncel mimari talimat değildir.
> Güncel karar için `ADR-003-opencore-kanonik-dagitim-kurulum-yasam-dongusu.md`
> dosyasına bakın.

## 1. Önceki Updater / Artifact Yaklaşımı

OpenCore geliştirme sürecinde bir dönem:

- ayrı application/vendor artifact'ları,
- release builder,
- manifest,
- staging,
- updater lock/state,
- filesystem journal,
- rollback/recovery mekanizmaları

üzerinde çalışılmıştır.

Bu yaklaşım terk edilmiştir.

Güncel mimaride repository/stable source tree dağıtılabilir ürünün kendisidir ve
runtime application/vendor self-update yapmaz.

## 2. Tarihsel Vendor Lifecycle Ayrıntıları

External storage kullanılan kurulumlarda geçmiş uygulama planlarında
`system/storage/vendor/` release payload'ının aktif `DIR_STORAGE/vendor/`
ile bootstrap öncesi değiştirilmesine yönelik ayrıntılı preflight davranışları
tanımlanmıştır.

Bu davranışın `rename()`, cross-filesystem fallback, copy/source-cleanup ve
autoload doğrulaması gibi implementation ayrıntıları ADR seviyesinde kalıcı
kural değildir.

Güncel karar yalnız şu sınırı korur:

- runtime vendor dependency OpenCore ile birlikte dağıtılır,
- external storage durumunda aktif runtime vendor dağıtılan release ile tutarlı olmalıdır,
- bu süreç runtime self-updater veya plugin/package sistemi oluşturmamalıdır.

## 3. Tarihsel Dağıtım Ağacı

Geçmiş uygulama planlarında örnek ürün ağacı şu biçimde gösterilmiştir:

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

Bu liste belirli bir geliştirme anındaki audit çıktısıdır. Güncel repository'nin
eksiksiz dosya listesi olarak kullanılmamalıdır.

## 4. Tarihsel Database Revision Ayrıntıları

Önceki ADR sürümünde belirli bir anda:

- current canonical baseline `1`,
- `Upgrade2`,
- legacy `notification.status` cleanup,
- `notification_target`,
- `notification_user`,
- notification expiry setting,
- notification cleanup cron seed'i

gibi implementation-specific ayrıntılar yer almıştır.

Bunlar ADR'nin kalıcı mimari kararı değildir.

Güncel kalıcı karar:

- application version ile database version ayrıdır,
- kurulu revision `oc_setting` içindeki `system/database_version` ile takip edilir,
- revision pozitif ve monoton integer'dır,
- database değişiklikleri source-controlled upgrade adımlarıyla ilerler.

Belirli revision içerikleri source code ve ilgili aktif implementation
dokümanlarından takip edilmelidir.

## 5. Tarihsel Notification Core Ayrıntıları

Önceki ADR sürümünde notification görünürlüğü, `notification_target`,
`notification_user`, status değerleri, index/unique key'ler ve expiry cron'u
ayrıntılı olarak tanımlanmıştır.

Bu bilgiler dağıtım/kurulum ADR'sinin konusu değildir.

Notification davranışı gerekiyorsa ilgili aktif kod veya ayrı plan/doküman
üzerinden yönetilmelidir.

## 6. Tarihsel Diagnostics Ayrıntıları

Önceki ADR sürümünde diagnostics için:

- version,
- database version,
- PHP/database server,
- extension'lar,
- upload/execution limitleri,
- application/storage/install yolları,
- directory writability,
- yeşil/turuncu/kırmızı seviyeleri

gibi ayrıntılı alanlar listelenmiştir.

ADR seviyesindeki kalıcı karar yalnız diagnostics'in gözlemsel olması ve updater
veya deployment engine'e dönüşmemesidir.

## 7. Tarihsel Manuel Update Akışı

Önceki belgelerde manuel update örneği şu şekilde tanımlanmıştır:

```text
stable release bildirimi
-> stable source archive indirme
-> application/database backup
-> application dosyalarını deploy etme
-> external storage vendor uyumluluğunu sağlama
-> DB işi gerekiyorsa install/upgrade
```

Bu kayıt yaklaşımın tarihsel açıklamasıdır. Güncel görevler aktif ADR, plan ve
release dokümantasyonuna göre yürütülmelidir.

## 8. Terk Edilmiş veya Reddedilmiş Yapılar

Geçmiş tasarım tartışmalarında aşağıdaki yapılar değerlendirilmiş ve kanonik
mimarinin dışında bırakılmıştır:

- runtime application/vendor self-updater
- runtime release download ve staging
- manifest-controlled application mutation
- updater filesystem journal / lock / recovery state
- bridge release
- özel distribution ZIP
- product-repository release builder
- ayrı vendor artifact
- vendor activation pointer / swap
- production Composer
- runtime extension / Marketplace / OCMOD installation
- updater-specific structured backup evidence
- updater lock olarak maintenance state kullanımı

Bu liste tarihsel bağlam içindir; güncel geliştirme talimatı olarak kullanılmamalıdır.

## 9. Önceki ADR Numaralandırması

Bu karar daha önce `ADR-006` numarasıyla tutuluyordu.

Aktif ADR'lerin ardışık numaralandırılması kararıyla yeni numarası `ADR-003`
olmuştur.

Eski dokümanlardaki `ADR-006` referansları güncel dokümantasyon temizliği sırasında
`ADR-003` olarak güncellenmelidir.
