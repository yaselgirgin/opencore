# ADR-001 Tarihsel Uygulama KaydÄ± â€” E-Ticaret TemizliÄŸi

> **Durum:** Historical
>
> Bu dosya ADR-001'in geÃ§miÅŸ uygulama sÃ¼recinde kullanÄ±lan Ã§alÄ±ÅŸma notlarÄ±nÄ±, aÅŸamalarÄ±,
> checklist'leri ve eski Codex talimatlarÄ±nÄ± saklar. GÃ¼ncel mimari veya geliÅŸtirme talimatÄ± deÄŸildir.
> GÃ¼ncel karar iÃ§in `ADR-001-opencart-eticaret-katmaninin-kaldirilmasi.md` dosyasÄ±na bakÄ±n.

## Tarihsel Uygulama YaklaÅŸÄ±mÄ±

E-ticaret temizliÄŸi sÄ±rasÄ±nda kullanÄ±lan yaklaÅŸÄ±m:

1. Referans ve baÄŸÄ±mlÄ±lÄ±k envanteri Ã§Ä±karÄ±lmasÄ±
2. Korunacak Ã§ekirdek bileÅŸenlerin belirlenmesi
3. Stok e-ticaret menÃ¼ ve route'larÄ±nÄ±n kaldÄ±rÄ±lmasÄ±
4. Storefront akÄ±ÅŸÄ±nÄ±n kapatÄ±lmasÄ±
5. KullanÄ±lmayan e-ticaret dosyalarÄ±nÄ±n alan bazlÄ± kaldÄ±rÄ±lmasÄ±
6. Kalan route/model/language/view/event/cron referanslarÄ±nÄ±n temizlenmesi
7. Database tablolarÄ±nÄ±n ayrÄ± ve geri alÄ±nabilir bir Ã§alÄ±ÅŸma olarak deÄŸerlendirilmesi

## Tarihsel Envanter Ã‡Ä±ktÄ±larÄ±

Temizlik sÄ±rasÄ±nda aÅŸaÄŸÄ±daki envanterler kullanÄ±ldÄ±:

- `docs/history/cleanup/file-inventory.md`
- `docs/history/cleanup/route-inventory.md`
- `docs/history/cleanup/table-inventory.md`

Bu belgeler de tarihsel iÃ§eriktir ve aktif geliÅŸtirme talimatÄ± olarak kullanÄ±lmamalÄ±dÄ±r.

## Tarihsel Dosya SÄ±nÄ±flandÄ±rmasÄ±

Temizlik sÄ±rasÄ±nda dosyalar ÅŸu sÄ±nÄ±flarla deÄŸerlendirilmiÅŸtir:

- `CORE`
- `ECOMMERCE-STOCK`
- `CUSTOM-BUSINESS`
- `SHARED`
- `UNKNOWN`

Bu sÄ±nÄ±flandÄ±rma, dosyalarÄ±n yalnÄ±z adÄ±na bakÄ±larak silinmesini Ã¶nlemek iÃ§in kullanÄ±lmÄ±ÅŸtÄ±r.

## Tarihsel VeritabanÄ± PolitikasÄ±

Ä°lk e-ticaret temizliÄŸi sÄ±rasÄ±nda tablolar doÄŸrudan silinmemiÅŸtir.

Tablolar ÅŸu sÄ±nÄ±flarla deÄŸerlendirilmiÅŸtir:

- `KEEP`
- `MIGRATE`
- `DROP-CANDIDATE`

Database silme/deÄŸiÅŸiklik iÅŸleri ayrÄ± karar ve kontrollÃ¼ uygulama kapsamÄ±nda ele alÄ±nmÄ±ÅŸtÄ±r.

## Tarihsel Test YaklaÅŸÄ±mÄ±

Temizlik sÃ¼recinde aÅŸaÄŸÄ±daki alanlar kontrol edilmiÅŸtir:

- login
- user / user_group
- permission
- ortak application shell
- API davranÄ±ÅŸÄ±
- kaldÄ±rÄ±lan route'lara kalan referanslar
- PHP syntax
- runtime log hatalarÄ±

Bu liste gÃ¼ncel tÃ¼m gÃ¶revler iÃ§in zorunlu bir test checklist'i deÄŸildir.

## Tarihsel Commit YaklaÅŸÄ±mÄ±

Temizlik Ã§alÄ±ÅŸmasÄ± kÃ¼Ã§Ã¼k ve geri alÄ±nabilir batch'lere bÃ¶lÃ¼nerek yÃ¼rÃ¼tÃ¼lmÃ¼ÅŸtÃ¼r.

Eski commit planlarÄ± ve gÃ¶rev sÄ±ralamalarÄ± bugÃ¼nkÃ¼ geliÅŸtirme akÄ±ÅŸÄ±nÄ± baÄŸlamaz.

## Eski Codex Uygulama TalimatÄ±

ADR-001'in Ã¶nceki sÃ¼rÃ¼mÃ¼nde doÄŸrudan Codex'e verilebilecek geniÅŸ kapsamlÄ± bir uygulama prompt'u bulunuyordu.

Bu prompt artÄ±k aktif talimat deÄŸildir.

Yeni gÃ¶revler:

- yalnÄ±z gÃ¼ncel task kapsamÄ±na gÃ¶re,
- ilgili ADR ve planlar okunarak,
- `AGENTS.md` Ã§alÄ±ÅŸma kurallarÄ±na uygun

ÅŸekilde hazÄ±rlanmalÄ±dÄ±r.

