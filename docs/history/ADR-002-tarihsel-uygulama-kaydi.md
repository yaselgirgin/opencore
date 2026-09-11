# ADR-002 Tarihsel Uygulama Kaydı — Extension, Marketplace ve OCMOD Temizliği

> **Durum:** Historical
>
> Bu dosya ADR-002'nin geçmiş uygulama sürecinde kullanılan migration yaklaşımını,
> audit sınıflandırmalarını ve cleanup notlarını saklar. Güncel mimari veya geliştirme
> talimatı değildir. Güncel karar için
> `ADR-002-runtime-extension-marketplace-ocmod-mimarisi-yok.md` dosyasına bakın.

## Tarihsel Capability Sınıflandırması

Root `extension/` altında bulunan capability'ler migration sırasında şu sınıflarla değerlendirilmiştir:

- `PROMOTE_TO_CORE`
- `DELETE`
- `TEMPORARILY_KEEP_DURING_MIGRATION`

Bu sınıflandırma yalnız tarihsel migration çalışmasını yönlendirmek için kullanılmıştır.

## Tarihsel Event Sınıflandırması

Event kayıtları ve action'lar migration sırasında şu sınıflarla değerlendirilmiştir:

- `CORE_EVENT`
- `EXTENSION_EVENT`
- `MODIFICATION_EVENT`
- `STALE_EVENT`

Bu sınıflandırma güncel tüm görevlerde zorunlu bir yöntem değildir.

## Tarihsel Database Risk İşareti

Dinamik veya güvenle sınıflandırılamayan eski kayıtlar migration sırasında:

`DB_DYNAMIC_STALE_RISK`

olarak işaretlenebilmiştir.

Bu bir güncel database mimarisi değildir.

## Tarihsel Geçiş Stratejisi

Extension/Marketplace/OCMOD temizliği aşağıdaki fazlarla planlanmıştır:

1. Extension, Marketplace, modification, event, startup, permission, filesystem ve DB referanslarının envanteri
2. Gerekli capability'lerin core alanlara taşınması
3. Kullanılmayan generic extension-type UI/controller yüzeylerinin kaldırılması
4. Marketplace ve runtime install/update/discovery akışlarının kaldırılması
5. OCMOD ve runtime modification yüzeylerinin kaldırılması
6. Root `extension/` ağacının kaldırılması
7. Config, startup, event, route ve permission artıklarının temizlenmesi
8. External storage ve eski DB durumunun ayrıca değerlendirilmesi

Bu fazlar tamamlanmış/geçmiş migration çalışmasının kaydıdır; yeni task planı olarak kullanılmamalıdır.

## Tarihsel Cleanup Güvenlik Yaklaşımı

Geçiş sırasında kaynak kaldırma işlemlerinden önce ilgili controller, model, startup,
event, generated runtime, database ve external-storage bağımlılıklarının doğrulanması
öngörülmüştür.

Repository source cleanup ile external storage cleanup ayrı operasyonlar olarak
değerlendirilmiştir.

Bu notlar güncel görevlerde `AGENTS.md`, aktif ADR'ler ve ilgili planların yerine geçmez.

## Tarihsel Kapsam Dışı Notları

Eski migration planı aşağıdaki alanları bu çalışmanın dışında tutmuştur:

- frontend build tool seçimi
- replacement package/plugin framework
- custom module system tasarımı
- PHP upgrade
- database schema redesign
- event sisteminin tamamen kaldırılması
- localisation veya currency capability kaldırılması
- captcha veya security capability kaldırılması

Bu liste tarihsel migration kapsamını belgelemek içindir.
