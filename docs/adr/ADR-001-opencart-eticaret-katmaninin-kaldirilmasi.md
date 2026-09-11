# ADR-001: OpenCart E-Ticaret Katmanının Kaldırılması

- **Durum:** Accepted
- **Tarih:** 2026-08-05
- **Karar sahipleri:** Proje sahibi / teknik ekip
- **Hedef:** OpenCore

## 1. Bağlam

OpenCore, OpenCart 4.1.0.4 altyapısından türetilmiştir ancak bir e-ticaret mağazası değildir.

OpenCart'tan devralınan çekirdek çalışma prensipleri korunurken stok e-ticaret işlevlerinin OpenCore kaynak ağacında tutulmasına ihtiyaç yoktur.

OpenCore'un kanonik uygulama sınırları:

- `app/`: yönetim / şirket içi uygulama
- `api/`: API uygulaması
- `system/`: ortak runtime ve altyapı
- `install/`: kurulum ve database upgrade uygulaması

OpenCart'tan devralınan loader, router, event, helper, library ve MVC çalışma ilkeleri korunur.

Kanonik geliştirme modeli:

`Controller → Model → Language → View`

## 2. Karar

OpenCart'ın stok e-ticaret katmanı OpenCore'dan kaldırılmıştır ve yeniden eklenmeyecektir.

Bu kapsam özellikle şunları içerir:

- storefront
- product / category mağaza akışları
- cart ve checkout
- payment ve shipping
- storefront customer account
- order / return / subscription e-ticaret akışları
- coupon, voucher, reward ve affiliate
- storefront theme, layout, search ve benzeri mağaza yüzeyleri
- stok e-ticaret raporları
- e-ticaret odaklı dashboard, event, cron ve API yüzeyleri

Bir dosya veya veri yapısı yalnız adına bakılarak kaldırılmaz. OpenCore'un ortak altyapısı veya şirket içi işlevleri tarafından kullanılan bileşenler korunur veya ilgili görev kapsamında ayrıştırılır.

## 3. Korunan OpenCart Temeli

Aşağıdaki türde ortak altyapılar OpenCore'un temelinin parçası olmaya devam eder:

- Registry
- Loader
- Router / Action
- Controller
- Model
- Event
- Config
- Request / Response
- Session
- Cache
- Log
- Database sürücüleri
- Language
- URL
- Template / Twig
- helper ve library mekanizmaları
- user ve `user_group` permission altyapısı
- ortak file / upload altyapısı
- OpenCore tarafından kullanılan diğer ortak runtime bileşenleri

Mevcut OpenCore/OpenCart çekirdeğinde karşılığı bulunan bir mekanizma gereksiz yere yeniden oluşturulmaz.

## 4. Uygulama Sınırları

Yeni geliştirmelerde eski OpenCart uygulama dizinleri kullanılmaz:

- `admin/`
- `catalog/`

OpenCore'un fiziksel uygulama dizinleri:

- `app/`
- `api/`

Site kökü `/` yönetim uygulamasını çalıştırır.

API `/api/` sınırı altında çalışır.

Eski storefront davranışı veya legacy `admin/` / `catalog/` uygulama yapısı geri getirilmez.

## 5. Mimari Sınırlar

Bu karar aşağıdaki yeni mimari katmanları getirmez:

- Service layer
- Repository layer
- ORM
- custom module loader
- plugin framework
- yeni routing framework

Böyle bir temel mimari değişiklik ancak ayrı owner kararıyla yapılabilir.

SQL `Model` katmanında tutulur. Kullanıcıya görünen metinler `Language` yapısıyla yönetilir.

## 6. Diğer ADR'lerle İlişki

Runtime extension, Marketplace ve OCMOD mimarisinin kaldırılması **ADR-002** tarafından tanımlanır.

Dağıtım, kurulum, storage/vendor yaşam döngüsü, manuel application update ve database-only `install/upgrade` mimarisi **ADR-003** tarafından tanımlanır.

Bu ADR yalnız OpenCart e-ticaret katmanının kaldırılması ve korunan OpenCart çekirdek yaklaşımının sınırını tanımlar.

## 7. Tarihsel Uygulama Kayıtları

Bu kararın uygulanması sırasında kullanılan eski envanterler, temizlik aşamaları, test checklist'leri, commit önerileri ve Codex uygulama talimatları güncel mimari talimat değildir.

Bu tarihsel materyal `docs/history/` altında tutulur.
