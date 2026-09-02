# OpenCore

OpenCore, OpenCart 4.1.0.4 mimarisini izleyen kurum içi uygulama platformudur. Uygulama
Controller -> Model -> Database akışını kullanır; e-ticaret vitrini, Marketplace,
OCMOD, çalışma zamanında extension kurulumu ve runtime self-updater kapsam dışıdır.

Repository ve stable release tag'leri kurulabilir dağıtımın kendisidir. Stable tag için
üretilen source archive, ayrı release builder veya vendor paketi olmadan standart
kurulum medyasıdır.

## Gereksinimler

- PHP 8.1 veya üzeri
- MySQLi ile erişilebilen MySQL ya da MariaDB
- PHP dosya yükleme desteği ve yazılabilir runtime storage dizinleri
- İsteğe bağlı stable release denetimi için cURL ve HTTPS erişimi

Runtime bağımlılıkları `system/storage/vendor/` içinde dağıtılır. Production ortamında
Composer, SSH, shell erişimi ya da runtime dependency çözümleme gerekmez.

## Yeni kurulum

1. Resmî stable source archive'i web köküne açın; `system/storage/vendor/` içeriğini
   koruyun.
2. Web sunucusunun `config.php` ve varsayılan `system/storage/` altındaki gerekli
   runtime dizinlerine yazabilmesini sağlayın.
3. Tarayıcıdan `install/` yolunu açın, lisansı kabul edin ve veritabanı ile ilk yönetici
   bilgilerini girin. Installer yalnız kök `config.php` dosyasını üretir.
4. Kurulumdan sonra `app/` içindeki Security akışından `install/` dizinini kaldırın
   ve isterseniz storage'ı web kökü dışına taşıyın.

Kurulum yapılmış bir sistemde fresh installer yeniden kurulum yapmaz. Varsayılan
`app/`, `system/storage/` ve mevcut `install/` dizini desteklenir; bunlar tek başına
runtime hatası değildir. Eski `/admin/` ve `/catalog/` HTTP yolları kullanılamaz.

## Yapılandırma ve storage

OpenCore yalnız kök `config.php` kullanır; ayrı bir application config dosyası yoktur.
Varsayılan storage `system/storage/` olup runtime daima `DIR_STORAGE` kullanır.

Storage web kökü dışına taşınırsa `DIR_STORAGE`, vendor dahil bütün storage ağacını
göstermelidir. Storage taşıma post-install Security akışındaki isteğe bağlı işlemdir.

## Yedekleme ve geri yükleme

`app/` içindeki Backup aracı SQL yedeklerini `DIR_STORAGE/backup/` altında yönetir. Geri
yükleme veri değiştirir: önce uygun bir yedeği ve bakım/recovery planını hazırlayın,
ardından işlemi yetkili yöneticiyle başlatın. Yedek dosyalarını source control'e eklemeyin.

## Manuel uygulama güncellemesi

OpenCore, application veya vendor dosyalarını indiren, stage eden ya da geri alan bir
runtime self-updater değildir; application güncellemesi operatörün sorumluluğundadır.
Yalnız external `DIR_STORAGE` kullanımında, deploy edilmiş release payload'ındaki
`system/storage/vendor/` için bootstrap preflight aktif `DIR_STORAGE/vendor/` ağacını
release-owned replacement olarak uygular. Bu istisna dışında runtime application veya
vendor dosyalarını değiştirmez.

1. Yeni stable source archive'i indirin ve kurulum ile veritabanının yedeğini alın.
2. Yerel `config.php` ve kalıcı runtime verilerini koruyarak yeni application dosyalarını
   manuel deploy edin.
3. `DIR_STORAGE` web kökü dışındaysa, release ile yeniden gelen
   `system/storage/vendor/` ilk bootstrap sırasında aktif `DIR_STORAGE/vendor/` ile
   tamamen değiştirilir. Bu işlem yalnız vendor içindir; cache, logs, session, upload ve
   backup dizinlerine dokunmaz.
4. Release için veritabanı işi gerekiyorsa `install/upgrade` akışını çalıştırın.

External storage kullanan operatörler vendor için ayrıca merge veya senkronizasyon
yapmaz. Bootstrap, yeni `vendor/autoload.php` doğrulanmadan devam etmez.

## Veritabanı yükseltmesi

`install/upgrade`, yalnız kurulu OpenCore veritabanındaki açık schema/data adımlarını
çalıştırır. Application veya vendor dosyalarına dokunmaz. Uygulama sürümü
`system/version.php` içindeki `VERSION`; veritabanı seviyesi ise `oc_setting` içindeki
`system/database_version` değeridir. Sürüm uyuşmazlığında uygulama/installer, gerekli
DB-only yükseltme akışına yönlendirir veya fail-closed davranır.

Yükseltmeden önce yedeği aldığınızı onaylayın. Eksik, geçersiz veya geri yönde
veritabanı revision'ları için yükseltme yapılmaz.

## Bildirimler, release denetimi ve tanılama

Bildirim çekirdeği global veya kullanıcı/kullanıcı-grubu hedefli bildirimleri destekler.
Kullanıcı bildirimleri okunmuş veya dismissed olarak işaretleyebilir; süreli kayıtlar
günlük `notification_cleanup` cron işiyle temizlenir.

Settings -> System Diagnostics, kurulu sürümü, veritabanı uyumluluğunu, ortamı,
yolları/yazılabilirliği ve event çözümlemesini salt okunur olarak gösterir. Yeşil sağlıklı,
turuncu öneri, kırmızı gerçek sorun anlamındadır; varsayılan app/storage yolları ve
mevcut `install/` dizini otomatik hata sayılmaz.

Bu ekrandaki release denetimi GitHub'daki taslak olmayan prerelease olmayan en yeni
stable release'i el ile kontrol eder. Daha yeni sürüm için yalnız bir kez global,
bilgilendirici bildirim oluşturabilir ve release sayfasına bağlantı verebilir; hiçbir
dosyayı, vendor'ı veya veritabanını indirmez ya da değiştirmez.

## Lisans

OpenCore, [GNU General Public License v3.0](LICENSE) ile lisanslanmıştır.
