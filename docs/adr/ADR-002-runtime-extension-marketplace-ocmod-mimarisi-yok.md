# ADR-002: Runtime Extension, Marketplace ve OCMOD Mimarisi Yok

- **Durum:** Accepted
- **Tarih:** 2026-08-21
- **Karar sahipleri:** Proje sahibi / teknik ekip
- **Hedef:** OpenCore

## 1. Bağlam

OpenCore, OpenCart 4.1.0.4 altyapısından türetilmiş şirket içi operasyonel çalışma, koordinasyon ve karar destek platformudur.

OpenCart'tan kalan extension discovery, Marketplace, runtime installer/update ve OCMOD mekanizmaları bu hedef mimariyle uyumlu değildir. Bu mekanizmalar uygulama davranışının deploy edilmiş kaynak kod dışında değişebilmesine, ek route ve permission yüzeylerine ve runtime sırasında kaynak kod veya dosya sistemi mutasyonuna izin verir.

ADR-001 stok e-ticaret katmanının kaldırılmasını tanımlar.

Dağıtım, kurulum, storage/vendor yaşam döngüsü ve database upgrade modeli ADR-003 tarafından tanımlanır.

Bu ADR, OpenCore'da application feature delivery sınırını belirler.

## 2. Karar

OpenCore bir runtime plugin veya extension platformu olmayacaktır.

Yeni işlevler:

- OpenCore source tree içinde,
- source control altında,
- mevcut OpenCore/OpenCart-derived mimariye entegre edilerek

geliştirilir.

Kanonik geliştirme modeli:

`Controller → Model → Language → View`

Runtime sırasında application özelliği kuran veya kaynak kodu değiştiren ayrı bir extension/plugin mekanizması kullanılmaz.

## 3. Kaldırılan Mekanizmalar

OpenCore'un kanonik mimarisinde aşağıdaki mekanizmalar bulunmaz:

- root `extension/` tree
- OpenCart extension discovery
- runtime extension install / uninstall / update
- Marketplace UI, catalog, download ve install akışları
- OCMOD
- runtime modification application
- generic extension-type administration yüzeyleri
- extension package tabanlı feature delivery
- extension tarafından runtime dependency veya vendor mutation

Bu mekanizmalar yeni geliştirmelerde yeniden oluşturulmaz.

## 4. OpenCore İçinde Korunabilecek Kabiliyetler

Bir işlevin geçmişte `extension/` altında bulunmuş olması, o işlevin mutlaka kaldırılması gerektiği anlamına gelmez.

OpenCore için gerçekten gerekli ortak bir capability mevcutsa, extension bağımlılıklarından ayrılarak mevcut kanonik alanlardan uygun olanına taşınabilir:

- `app/`
- `api/`
- `system/library/`
- `system/helper/`
- `system/config/`
- mevcut diğer OpenCore/OpenCart-derived core alanları

Taşınan kod extension namespace, discovery, loader veya runtime installation contract'ına bağlı kalmamalıdır.

Yeni bir replacement plugin framework veya custom module loader oluşturulmaz.

## 5. Marketplace

Marketplace bir OpenCore capability'si değildir.

Marketplace'e ait:

- yönetim yüzeyleri
- remote extension catalog
- download
- install
- update
- runtime filesystem mutation

davranışları OpenCore'un kanonik mimarisinin parçası değildir.

## 6. OCMOD

OpenCore OCMOD kullanmaz.

Aşağıdaki modification-specific mekanizmalar kanonik mimarinin parçası değildir:

- modification administration
- modification XML/application mekanizması
- generated modification tree/cache
- modification refresh
- modification-specific startup/config hook'ları
- modification-specific event entegrasyonu

## 7. Event ve Startup Sınırı

Event ve startup sistemleri extension veya OCMOD ile eş anlamlı değildir.

OpenCore'un kendi çalışma biçimi için gerekli core event ve startup mekanizmaları korunabilir.

Yalnız Marketplace, extension veya modification altyapısına özgü davranışlar bu karar kapsamında kaldırılmış kabul edilir.

Event veya startup altyapısının tamamen kaldırılması bu ADR'nin kararı değildir.

## 8. Dağıtım Sınırı

Runtime extension installation bir deployment yöntemi değildir.

Application özellikleri source-controlled OpenCore koduyla teslim edilir.

Production arayüzünden kod, extension veya Marketplace paketi kurulmaz ve application kaynakları runtime tarafından güncellenmez.

Dependency ve vendor yaşam döngüsü ADR-003'te tanımlanan dağıtım modeline tabidir.

## 9. Database ve Storage Sınırı

Bu ADR tek başına database schema, kayıt veya storage dosyası silme yetkisi vermez.

Tarihsel extension, Marketplace veya OCMOD kalıntılarının fiziksel temizliği gerektiğinde:

- mevcut kullanım doğrulanır,
- ilgili dependency incelenir,
- database veya storage değişikliği görev kapsamında ayrıca ele alınır.

Ortam veya makineye özgü storage path'leri bu ADR'nin mimari kararının parçası değildir.

## 10. Mimari Sınırlar

Bu ADR aşağıdaki yeni yapıları getirmez:

- Service layer
- Repository layer
- ORM
- plugin framework
- custom module loader
- yeni extension framework
- yeni routing framework

Bunlar ancak ayrı owner kararıyla eklenebilir.

## 11. Diğer ADR'lerle İlişki

ADR-001, OpenCart e-ticaret katmanının kaldırılmasını ve korunan OpenCart çekirdek yaklaşımını tanımlar.

ADR-003, OpenCore'un kanonik dağıtım, kurulum, storage/vendor yaşam döngüsü ve database upgrade modelini tanımlar.

Bu ADR yalnız runtime extension, Marketplace, OCMOD ve application feature delivery sınırından sorumludur.

## 12. Tarihsel Uygulama Kayıtları

Bu kararın uygulanması sırasında kullanılan migration fazları, audit sınıflandırmaları, cleanup listeleri ve eski operasyonel checklist'ler güncel mimari talimat değildir.

Bu tarihsel materyal `docs/history/` altında tutulur.
