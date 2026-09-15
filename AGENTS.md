# AGENTS.md

## 1. Proje Tanımı

OpenCore bir ERP değildir.

OpenCore; şirket içi operasyonel çalışma, koordinasyon ve karar destek platformudur.

Gerektiğinde dış sistemler, API'ler, yapay zekâ ajanları ve diğer veritabanlarıyla veri alışverişi yaparak çalışabilir.

Dış sistemlere özgü kod, OpenCore çekirdeğine gereksiz şekilde yayılmamalı; mevcut OpenCore mimarisi içinde mümkün olduğunca ayrık tutulmalıdır.

## 2. OpenCart Temeli

OpenCore, OpenCart 4.1.0.4 altyapısından türetilmiştir.

OpenCart'tan devralınan temel çalışma prensipleri korunur:

- `system/`
- `engine/`
- `helper/`
- `library/`
- `storage/`
- loader ve router yapısı
- event ve ortak runtime mekanizmaları
- OpenCart/OpenCore MVC yaklaşımı

OpenCore artık OpenCart ile birebir parity hedeflemez.

Aşağıdaki OpenCart alanları bilinçli olarak kaldırılmıştır ve yeniden eklenmemelidir:

- e-commerce / storefront
- Marketplace
- OCMOD
- runtime self-update

Gerektiğinde OpenCart 4.1.0.4 upstream kaynakları, korunan altyapının çalışma şeklini anlamak için referans olarak kullanılabilir.

Yeni bir ihtiyaç mevcut `engine`, `helper`, `library` veya diğer ortak mekanizmalarla çözülebiliyorsa paralel bir sistem oluşturulmaz.

Gerçek bir ortak ihtiyaç varsa yeni helper veya library eklenebilir; ancak mevcut OpenCart/OpenCore çalışma prensiplerine uygun geliştirilmelidir.

## 3. Kanonik Uygulama Yapısı

Yönetim uygulamasının fiziksel uygulama dizini:

`app/`

API uygulamasının fiziksel uygulama dizini:

`api/`

Site kökü `/`, yönetim uygulamasını çalıştırır.

API sınırı `/api/` altında çalışır.

Eski `admin/` ve `catalog/` uygulama yapıları yeni geliştirmelerde kullanılmaz.

OpenCore ürün ve runtime işlevleri CLI kullanımına bağımlı olmamalıdır. Kurulum, upgrade, cron ve benzeri operasyonel akışlar HTTP üzerinden çalışabilmelidir.

Bu kural geliştiricinin Git, PHP syntax check veya benzeri geliştirme amaçlı CLI araçlarını kullanmasını engellemez.

## 4. Geliştirme Mimarisi

Kanonik geliştirme modeli:

`Controller → Model → Language → View`

SQL yalnız `Model` içinde bulunur.

`Controller` veya `View` içine doğrudan SQL yazılmaz.

Mevcut yapıda karşılığı olmayan yeni mimari katmanlar veya framework'ler, owner açıkça onaylamadan eklenmez.

Yeni bir üçüncü taraf library veya package ancak mevcut OpenCore/OpenCart altyapısıyla makul şekilde çözülemeyen gerçek bir ihtiyaç varsa ve owner onayladıktan sonra eklenebilir.

Mevcut OpenCore/OpenCart kod stili ve isimlendirme yaklaşımı korunur.

Route, class, method, variable, file ve database isimlendirmelerinde ayrı bir standart oluşturulmaz.

Mevcut hata yönetimi ve loglama mekanizmaları kullanılır; paralel bir logging sistemi kurulmaz.

## 5. Ortak Altyapının Kullanımı

Yeni modül ve özelliklerde mevcut ortak altyapılar öncelikle yeniden kullanılmalıdır.

Örnekler:

- user
- user_group
- permission
- notification
- file / upload
- cron
- mevcut helper ve library yapıları

Aynı işi yapan ikinci bir paralel altyapı oluşturulmamalıdır.

Yetkilendirme mevcut `user_group` `access/modify` permission sistemiyle yapılır.

Yeni kullanıcıya açık route ve menüler mevcut permission sistemine bağlanmalıdır.

Yeni database tabloları ve alanları mevcut OpenCore/OpenCart database yapı ve isimlendirme prensiplerini takip etmelidir.

Database schema veya gerekli source-controlled data değişiklikleri mevcut `install/upgrade` zincirine eklenmelidir.

## 6. Language ve UI

Kullanıcıya görünen metinler mevcut language mekanizmasıyla yönetilir.

Kullanıcıya görünen metinler `Controller` veya `View` içine hard-code edilmez.

Yeni kullanıcı arayüzlerinde en az:

- `tr-tr`
- `en-gb`

language karşılıkları bulunmalıdır.

UI görevlerinde güncel kanonik UI geliştirme planı takip edilir.

## 7. Görev Kapsamı

Yalnız verilen görevin kapsamı içinde çalış.

Görev kendi içinde gerekli küçük alt işlere bölünebilir ve geliştirici bunları kendi organize edebilir.

Ancak verilen görev yeni bir proje, yeni bir roadmap, ilgisiz bir refactor, genel repository temizliği veya ayrı bir geliştirme programı haline dönüştürülmemelidir.

Değişiklik yapmadan önce ilgili dosyayı ve doğrudan bağımlılıklarını incele.

İncelemeyi görev kapsamıyla sınırlı tut; tüm repository'yi varsayılan olarak tarama.

Görev için gerekli olmayan refactor veya temizlik yapılmaz.

Görev açıkça değiştirmeyi gerektirmiyorsa mevcut çalışan route, permission, data flow ve kullanıcı davranışı korunur.

Normal implementation kararlarını geliştirici verebilir.

Projenin temel mimarisini veya kabul edilmiş yapısal kararlarını değiştirmek gerekiyorsa dur ve owner'a sor.

## 8. Dokümantasyon

Proje dokümantasyonu Türkçe yazılır.

Teknik terimler, code, route, class, path ve benzeri teknik isimler gerektiğinde İngilizce kalabilir.

Yalnız verilen görevle ilgili ADR, plan ve diğer dokümanları oku.

Varsayılan olarak bütün proje dokümantasyonunu tarama.

Talimat ve karar önceliği:

1. Güncel ve açık owner talimatı
2. Accepted ADR
3. `AGENTS.md`
4. Güncel ilgili plan veya doküman
5. Mevcut OpenCore kodu ve çalışma biçimi
6. Gerektiğinde OpenCart 4.1.0.4 upstream referansı

Tarihsel belgeler `docs/history/` altında tutulur ve güncel mimari talimat olarak kullanılmaz.

Bir kod değişikliği mevcut bir ADR veya aktif planı geçersiz hale getiriyorsa, ilgili doküman aynı görev kapsamında güncellenmelidir.

Kod değişikliği dokümante edilmiş davranışı değiştirmiyorsa gereksiz doküman değişikliği yapılmaz.

## 9. Test ve Geçici Çalışma Alanları

OpenCore repository yalnızca gerçek proje kodu ve kalıcı proje dokümantasyonu içerir.

Geçici test dosyaları, scratch çalışmalar, debug scriptleri, dump'lar, loglar, ekran görüntüleri, geçici veri dosyaları, database exportları, yedekler ve yalnız tek görev için oluşturulan diğer geçici artefact'lar repository içinde oluşturulmaz.

Bu kural `.gitignore` altında tutulacak dosyalar için de geçerlidir. Repository geçici çalışma veya scratch alanı olarak kullanılmaz.

Geçici test ve doğrulama çalışmaları repository dışındaki görev bazlı geçici çalışma alanında yapılır.

Aynı görev için gereksiz yere birden fazla geçici çalışma alanı veya test database'i oluşturulmaz.

Önce implementation tamamlanır, ardından gerekli testler yapılır.

Ana OpenCore database'i yalnız veri değiştirmeyen kontroller için kullanılabilir.

Database değişikliği gerekiyorsa önce izole test database'inde doğrulanır.

Testler başarılı olduktan sonra mevcut ana database'e uygulanması gerekiyorsa owner onayı alınır.

Görev için oluşturulan geçici çalışma alanları, test dosyaları ve test database'leri görev sonunda temizlenir.

Repository içine yalnız OpenCore'un kalıcı bir parçası olması amaçlanan dosyalar eklenebilir.

## 10. Git

Geliştirici görevini tamamlayıp ilgili kontroller başarılı olduktan sonra commit oluşturabilir.

Owner onayı olmadan aşağıdaki işlemler yapılmaz:

- push
- reset
- rebase
- clean
- force işlemleri
- history değiştiren veya destructive Git işlemleri

Owner gerektiğinde kendi Git/publish workflow'unu ayrıca kullanabilir.

## 11. Görev Tamamlama

Görev tamamlandığında:

1. gerekli testleri tamamla,
2. testler başarılıysa commit oluştur,
3. kısa sonuç raporu ver,
4. dur.

Sonuç raporu yalnız şunları içermelidir:

- ne yapıldı
- değişen dosyalar
- yapılan testler ve sonuçları
- commit ID
- varsa açık kalan sorun veya owner kararı gereken konu

Yeni bir göreve kendiliğinden geçme.

## 12. İletişim

Owner-facing iletişim varsayılan olarak Türkçe olmalıdır.

Code, route, class, path, command ve diğer teknik ifadeler gerektiğinde İngilizce kalabilir.
