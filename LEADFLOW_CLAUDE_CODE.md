# LEADFLOW — Specifica operativa V1 e standard di sviluppo per Claude Code

> **Documento unico di riferimento per l'implementazione.** Leggere integralmente prima di modificare il progetto. Implementare il software, non limitarsi a produrre una proposta o altri documenti.
>
> **Progetto locale:** `C:\Users\giomm\Desktop\project\leadflow-be`
>
> **Stack già inizializzato:** Laravel 12, PHP compatibile con il progetto, Filament 5, MySQL (`leadflow`), pannello Filament `/admin`. Il database è raggiungibile dal `.env` locale. Esiste già almeno un utente creato attraverso `make:filament-user`.

---

## 1. Obiettivo e confini non negoziabili

Sviluppare **LeadFlow V1**, gestionale per acquisizione richieste da landing page esterne e lavorazione manuale tramite call center.

**Flusso completo della V1:**

1. L'amministratore crea un **prodotto**.
2. Crea un'**offerta** relativa a **un solo prodotto**.
3. Il gestionale genera un **codice `<script>` copiabile**, associato all'offerta.
4. Il codice viene consegnato a chi gestisce una landing page esterna e incollato nella pagina.
5. Lo script visualizza un **form responsive** all'interno di un iframe gestito dal nostro backend.
6. Il visitatore inserisce **nome, cognome, telefono e, facoltativamente, indirizzo** e invia una richiesta di ricontatto (non compra online).
7. Il sistema registra **direttamente un ordine** con stato `nuovo`, offerta collegata, prezzo unitario acquisito server-side e URL della pagina di origine.
8. Le operatrici `call_center` visualizzano tutti gli ordini. La prima che clicca **Prendi in carico** lo assegna a sé e lo porta a `in_lavorazione`.
9. L'operatrice assegnata chiama il cliente, può correggere anagrafica/indirizzo e imposta la **quantità**. Non può modificare il prezzo unitario.
10. L'operatrice conclude passando a `confermato` o `annullato`. Entrambi gli stati sono terminali nella V1.

**Non implementare ora:** Spedisci.online o altre API di spedizione; tracking; pagamenti; incassi; magazzino/giacenze; fingerprinting; CRM separato per lead; campagne Ads/Meta; statistiche avanzate; gestione multi-azienda; prodotti multipli per ordine; carrello; checkout; email transazionali; notifiche automatiche; stato `da_richiamare`.

Non introdurre servizi, tabelle, queue job, provider, astrazioni o librerie per queste funzionalità future prima che siano necessarie.

---

## 2. Principi ingegneristici obbligatori

- **SOLID, DRY, KISS e separazione delle responsabilità** applicati in modo pragmatico; niente overengineering.
- **Clean code:** nomi di classi, proprietà, metodi e colonne in **inglese**, parlanti e coerenti; messaggi/UI in **italiano**.
- **Thin controllers:** i controller validano e autorizzano attraverso Request/Policy, delegano la logica e restituiscono la risposta; non ospitano la business logic.
- **Thin Filament resources/pages/actions:** UI, query e configurazioni; le operazioni di dominio vengono delegate ad Actions/Services riutilizzabili.
- **Service layer / Actions:** una sola implementazione per ogni regola di business. Riutilizzare la stessa Action dalla UI e dagli endpoint quando opportuno.
- **Dependency injection** di dipendenze effettivamente necessarie; non creare interface/repository vuoti o wrapper di Eloquent senza motivazione concreta.
- **Eloquent idiomatico:** relazioni tipizzate, scopes utili, casts, eager loading e attenzione a N+1, query efficienti, indici appropriati.
- **Enums PHP backed** per stati ordine; mai proliferazione di stringhe magic in più classi.
- **Form Request** e **Policy/Gate** per validazione e autorizzazione effettiva lato server, anche nelle custom Filament Actions e richieste Livewire.
- **Database transactions** per gli aggiornamenti atomici. La presa in carico di un ordine deve essere realmente **concurrency-safe su MySQL**.
- **Tipizzazione e PHP moderno** compatibili con la versione PHP effettiva dell'ambiente; metodi chiari e piccoli dove utile.
- **Sicurezza by default:** mai fidarsi del browser per prezzi, permessi, offerta, stato o origine; sanitizzazione/escape corretto; protezione delle richieste pubbliche, rate limiting; niente log di password o dati sensibili non necessari.
- **Gestione errori:** eccezioni appropriate, risposte comprensibili, niente errori SQL mostrati agli utenti; messaggi di conferma/errori in italiano.
- **No regressioni:** prima ispezionare il repository, poi modificare solo ciò che serve. Non distruggere dati, non fare `migrate:fresh`, `db:wipe`, reset del database o ricreazione indiscriminata del progetto.
- **Qualità:** test automatizzati dei flussi critici; eseguire Laravel Pint, test suite e verifiche applicative; documentare sinteticamente come avviare e provare la V1.
- **Versioni reali:** verificare `composer.json`, `composer.lock`, `php -v` e le convenzioni delle versioni installate. Usare **esclusivamente API e namespace effettivi di Filament 5**, non esempi di Filament 3/4 riadattati a caso.

**Struttura architetturale suggerita (solo se utile, non obbligatoria):**

```text
app/
  Actions/Orders/        # presa in carico, conferma, annullamento e invio form
  Services/              # funzioni applicative condivise, quando aggiungono valore
  Enums/                 # OrderStatus
  Http/Controllers/      # loader script, embed form, ricezione form
  Http/Requests/         # validazione input pubblico
  Models/                # User, Product, Offer, Order
  Policies/              # autorizzazioni per risorsa e operazione
  Filament/Resources/    # Filament 5: utenti, prodotti, offerte, ordini
resources/views/         # form pubblico incorporabile
routes/                   # rotte pubbliche dedicate e rotte framework
```

Preferire il framework e un piccolo numero di classi con responsabilità nette rispetto a un'architettura cerimoniosa.

---

## 3. Autenticazione, ruoli e permessi

Un **solo pannello Filament**, quello già esistente, raggiungibile su `/admin`. Non creare un secondo pannello.

Integrare **Spatie Laravel Permission** nella versione compatibile con il PHP e Laravel realmente installati. Usare le sue migration standard per `roles`, `permissions` e tabelle pivot; trait `HasRoles` sul modello `User`. Evitare anche un campo `role` duplicato su `users`. Se utile, creare permessi granulari; l'autorizzazione centrale può usare ruoli e Policy, senza inventare configurazioni inutili.

### Ruoli

**`admin`**
- Accesso pieno a utenti, prodotti, offerte e ordini.
- Può creare/modificare/disattivare prodotti e offerte, visualizzare/copiare lo script, gestire gli utenti, vedere e amministrare tutti gli ordini, incluso riassegnare quelli in lavorazione.
- La V1 non deve avere un pulsante ordinario che riporti un ordine terminale in lavorazione; una riapertura richiederà una decisione futura.

**`call_center`**
- Accesso allo **stesso pannello Filament** ma **soltanto** alla sezione **Ordini**; non visualizza e non può accedere via URL a prodotti, offerte e gestione utenti.
- Vede **tutti** gli ordini (`nuovo`, `in_lavorazione`, `confermato`, `annullato`) in una tabella.
- Può prendere in carico solo un ordine **nuovo e non assegnato**.
- Può modificare dati cliente e quantità **solo** negli ordini in lavorazione **assegnati a lei**.
- Gli ordini assegnati ad altre operatrici sono **visibili soltanto in lettura**; non visualizzare il nome dell'operatrice assegnata nella UI call center.
- Gli ordini confermati o annullati sono **visibili in sola lettura** e non modificabili dalle operatrici.

### Regole accesso

- Login tramite email/password; **nessuna registrazione pubblica**.
- User inattivo (`is_active = false`) non può effettuare login/accedere al pannello, anche se possiede un ruolo.
- Controllare accesso pannello (`canAccessPanel`, se applicabile nella versione concreta), Resource Policy, query e **ogni action** di business. Nascondere il menu non costituisce sicurezza.
- L'admin crea o disabilita le utenze; non cancellare automaticamente ordini o riferimenti storici quando un utente viene disattivato.

### `users`

La tabella standard Laravel esiste già. **Modificare in modo additivo**, senza perdere account esistenti, per supportare i seguenti attributi; conservare le colonne standard di autenticazione Laravel:

| Colonna | Tipo indicativo | Regola |
|---|---|---|
| `id` | bigint unsigned PK | Auto incremento |
| `name` | varchar(100) | Obbligatorio |
| `surname` | varchar(100) | Preferibilmente obbligatorio per nuove utenze; migration compatibile con record preesistenti |
| `email` | varchar(255), unique | Login |
| `password` | varchar(255) | Hash Laravel |
| `phone` | varchar(30), nullable | Facoltativo |
| `is_active` | boolean, default true | Accesso abilitato/disabilitato |
| `last_login_at` | timestamp, nullable | Aggiornato al login quando pratico |
| `email_verified_at` | timestamp, nullable | Colonna standard |
| `remember_token` | varchar(100), nullable | Colonna standard |
| `created_at`, `updated_at` | timestamps | Standard |

L'account admin già creato manualmente **non deve essere cancellato o sovrascritto**. Se il passaggio ai ruoli lascia un vecchio account senza accesso, spiegarlo nelle istruzioni finali e fornire una modalità sicura per assegnargli esplicitamente il ruolo; **non attribuire privilegi automaticamente a indirizzi email arbitrari**.

---

## 4. Seeder DEMO: esclusivamente utenti e ruoli

**Obbligatorio:** fornire un seeder idempotente per soli **ruoli, eventuali permessi e due utenti demo**. Non creare seeder/factory di dati demo per prodotti, offerte o ordini; tali tabelle devono restare vuote dopo il setup.

Utenti richiesti:

| Ruolo | Nome | Cognome | Email |
|---|---|---|---|
| `admin` | LeadFlow | Admin | **`admin@leadflow.it`** |
| `call_center` | Giulia | Rossi | **`giulia.rossi@leadflow.it`** |

Specifiche:
- `is_active = true` per entrambi e ruolo assegnato correttamente con Spatie.
- Usare `updateOrCreate`/`firstOrCreate` in modo **idempotente** e **non distruttivo**. Non resettare a ogni esecuzione password modificate da utenti esistenti.
- **Nessuna password hardcoded nel codice/repository** e nessuna credenziale pubblicata nel file di specifiche. Usare variabili di `.env` locale, ad esempio `LEADFLOW_DEMO_ADMIN_PASSWORD` e `LEADFLOW_DEMO_CALL_CENTER_PASSWORD`, richiedendole o dando istruzioni precise per valorizzarle prima del seed; memorizzare soltanto gli hash.
- Se mancano credenziali, il seeder deve fallire con messaggio chiaro, non generare account con password prevedibile.
- Impostare un `DatabaseSeeder` che richiami i seed opportuni, e indicare il comando di avvio.
- Dopo il seed, verificare che entrambi gli account possano accedere al pannello con il rispettivo livello di autorizzazione.

---

## 5. Tabella `products`

| Colonna | Tipo indicativo | Regola |
|---|---|---|
| `id` | bigint unsigned PK | Standard |
| `name` | varchar(255) | Obbligatorio |
| `sku` | varchar(100), unique | Obbligatorio e univoco |
| `description` | text, nullable | Facoltativa |
| `image` | varchar(255), nullable | Una sola foto, path su disco `public` |
| `purchase_price` | decimal(10,2) | Obbligatorio, >= 0 |
| `selling_price` | decimal(10,2) | Obbligatorio, >= 0 |
| `is_active` | boolean, default true | Disattivazione senza perdere storico |
| `created_at`, `updated_at` | timestamps | Standard |

**UI Filament:** elenco con ricerca per nome/SKU, form creazione/modifica, upload immagine, stato attivo, prezzi formattati. Solo admin. Non gestire stock/quantità. Non permettere la cancellazione fisica di prodotti già associati a offerte/storici; usare disattivazione.

---

## 6. Tabella `offers`

Ogni offerta ha **un solo prodotto**.

| Colonna | Tipo indicativo | Regola |
|---|---|---|
| `id` | bigint unsigned PK | Standard |
| `name` | varchar(255) | Nome offerta |
| `product_id` | FK a `products.id` | Obbligatorio |
| `custom_price` | decimal(10,2), nullable | Se null, prezzo `products.selling_price`; altrimenti prezzo impostato dall'admin |
| `is_active` | boolean, default true | Se false, modulo disabilitato e ricezione negata |
| `public_token` | varchar(100), unique | Token pubblico **casuale, non indovinabile**; generato dal server |
| `created_at`, `updated_at` | timestamps | Standard |

Il **prezzo effettivo** per un ordine è `custom_price ?? product.selling_price`, calcolato **solo dal server al momento della ricezione**. Un cambio prezzo successivo non deve modificare ordini già creati.

**UI Filament:** elenco, ricerca, filtri attiva/disattiva, creazione/modifica, scelta prodotto attivo, opzione prezzo personalizzato e visualizzazione prezzo effettivo. Dettaglio con codice `<script>` **pronto da copiare** tramite pulsante. Lo script può essere incollato su **più landing diverse**.

Un'offerta disattivata deve disabilitare **sia il form sia l'endpoint POST**: non basta nascondere il form, e una pagina già aperta non deve poter inviare dopo la disattivazione. È preferibile impedire anche le richieste se il prodotto collegato viene disattivato; gestire questo comportamento in modo coerente tra GET e POST.

---

## 7. Tabella `orders`: unica entità per le richieste

**Non creare una tabella `leads` e non creare una tabella `order_items`.** Per la V1 ogni ordine corrisponde esclusivamente al prodotto dell'offerta selezionata.

| Colonna | Tipo indicativo | Regola |
|---|---|---|
| `id` | bigint unsigned PK | Standard |
| `offer_id` | FK a `offers.id` | Offerta di origine |
| `first_name` | varchar(100) | Obbligatorio |
| `last_name` | varchar(100) | Obbligatorio |
| `phone` | varchar(30) | Obbligatorio; validazione permissiva ma sicura |
| `address` | text, nullable | **Non obbligatorio nel form pubblico**, completabile da operatrice |
| `source_url` | varchar(2048), nullable | URL della landing esterna, raccolto dallo script e verificato come input non fidato |
| `quantity` | unsigned int, nullable | L'operatrice lo inserisce durante la chiamata; >= 1 per confermare |
| `unit_price` | decimal(10,2) | Prezzo risolto **server-side alla creazione**: copia immutabile per operatrice |
| `total_price` | decimal(10,2), nullable | `unit_price * quantity`; null se quantità non ancora impostata |
| `status` | varchar(30), default `nuovo` | Gestito da enum |
| `assigned_to` | FK nullable a `users.id` | Operatrice assegnata; non esposta nella UI call center |
| `created_at`, `updated_at` | timestamps | Standard |

Usare FK/indici su `offer_id`, `assigned_to`, `status` e `created_at` dove appropriato. Non aggiungere dati di spedizione/pagamento. Se sono disponibili soltanto nome, cognome e telefono, **la richiesta è valida** e genera l'ordine.

**Stati previsti, esclusivamente questi:**

- `nuovo`: creato dal form, senza assegnazione.
- `in_lavorazione`: l'operatrice lo ha preso in carico.
- `confermato`: ordine finale, non modificabile dall'operatrice.
- `annullato`: ordine finale, non modificabile dall'operatrice.

Transizioni regolari:

```text
nuovo --(Prendi in carico)--> in_lavorazione
in_lavorazione --(Conferma)--> confermato
in_lavorazione --(Annulla)--> annullato
```

In V1 non supportare altre transizioni. Per confermare richiedere **quantità >= 1** e, coerentemente con la conferma telefonica dell'indirizzo, **indirizzo valorizzato**. Mostrare errori di validazione comprensibili quando mancano. L'annullamento non richiede quantità.

**Regole di lavorazione:**

1. Tutte le operatrici vedono gli ordini nuovi e la lista completa.
2. Su un ordine `nuovo` è visibile l'azione **Prendi in carico**.
3. L'assegnazione modifica **atomicamente** `assigned_to` e `status`; due clic simultanei di persone diverse non devono poter produrre due assegnazioni valide. Usare transazione con lock/aggiornamento condizionale; ripetere la verifica stato/assegnazione lato server sotto lock.
4. Solo l'assegnataria può modificare anagrafica, telefono, indirizzo e quantità del proprio ordine `in_lavorazione`, e confermarlo/annullarlo.
5. Le altre operatrici vedono ordini altrui `in_lavorazione` ma non possono modificarli nemmeno invocando manualmente endpoint o action.
6. Non mostrare `assigned_to` come colonna al call center; conservarlo nel database e consentire riassegnazione admin, se necessaria.
7. Admin può vedere ogni ordine ed effettuare l'assegnazione/riassegnazione quando serve; lo stato finale resta di norma definitivo in V1.
8. Il prezzo unitario non è modificabile nel form pubblico né dal call center; il totale viene ricalcolato server-side quando cambia la quantità.
9. **Invii duplicati consentiti:** lo stesso numero di telefono può creare più ordini anche della medesima offerta; non introdurre vincolo UNIQUE sul telefono e non bloccare richieste solo perché i dati coincidono.

**UI Filament ordini:** tabella con ID, data, cliente, telefono, offerta, stato, origine (URL/host), prezzo e quantità se disponibile; ricerca e filtri utili; dettaglio consultabile da tutte le operatrici; editing/azioni soltanto quando consentito. L'interfaccia deve essere sobria, responsive e in italiano.

---

## 8. Script pubblico e form iframe — implementazione funzionante

**Requisito essenziale:** la piattaforma non genera soltanto del testo da copiare; deve esporre endpoint e risorse **realmente funzionanti**.

### Codice di incorporamento

Nel dettaglio offerta, generare un piccolo snippet, concettualmente:

```html
<script async src="https://DOMINIO-LEADFLOW/embed/offers/TOKEN.js"></script>
```

L'URL è **esemplificativo**: usare `APP_URL`/route naming del progetto, token effettivo e sintassi realmente funzionante. La possibilità di distribuire lo snippet a terze persone e incollarlo in qualsiasi landing autorizzata è un requisito.

Lo script esterno:

- Individua la pagina in cui viene incorporato, usando il contesto della **pagina host** (es. `window.location.origin + window.location.pathname`) e non il dominio dell'iframe.
- Crea un **iframe responsive** che visualizza un form ospitato da LeadFlow.
- Comunica all'iframe l'URL della landing di origine con una modalità semplice e robusta (per esempio parametro URL codificato); l'endpoint server conserva tale valore nell'ordine, previa validazione. La stessa offerta e lo **stesso codice** devono funzionare su landing differenti registrando URL differenti.
- Non richiede jQuery, build di asset sulla landing, cookie di terza parte o autenticazione dell'utente finale.
- Non interferisce con lo stile della landing esterna; scegliere dimensioni ragionevoli, larghezza 100%, layout mobile-first e, se necessario, ridimensionamento altezza sicuro tramite `postMessage` con controlli `origin` adeguati.
- Gestisce con messaggio non invasivo il caso di offerta disattivata.

**Nota sul tracciamento:** `source_url` è un dato dichiarato dal browser e quindi **non prova in modo crittografico la provenienza**. Va validato e trattato come metadato informativo. Conservare preferibilmente origine+percorso, evitando hash e query string con potenziali dati personali; non realizzare fingerprint o analytics invasivi. Se non si riesce a leggere un URL valido, non impedire il funzionamento essenziale: usare `null` e indicare in UI origine non disponibile.

### Form iframe

Campi mostrati:

1. **Nome** — required.
2. **Cognome** — required.
3. **Telefono** — required.
4. **Indirizzo** — optional.
5. Pulsante **Invia richiesta**.

Testo chiaro: **richiesta di ricontatto, non acquisto**. Mostrare una breve informativa/link privacy configurabile o adeguato placeholder di configurazione, senza inventare testi legali o introdurre consenso marketing obbligatorio non richiesto.

Dopo invio valido: messaggio **“Richiesta inviata con successo. Sarai ricontattato a breve.”**; disabilitare il pulsante durante l'invio per evitare doppi click accidentali (ma **non impedire** la creazione deliberata di nuove richieste con stessi dati).

### Endpoint e sicurezza

- Esporre rotta per script JS, rotta per HTML del form iframe, endpoint POST pubblico per invio dati.
- Utilizzare strategia **stateless** adatta a embed cross-site: non dipendere da sessioni/cookie third-party. Configurare correttamente middleware/CSRF per l'endpoint pubblico senza disabilitare indiscriminatamente protezioni di altre rotte. Non aggiungere Sanctum o sistema auth pubblico se non necessario.
- Consentire il rendering cross-site **solo della vista embed**, verificando `X-Frame-Options`/`Content-Security-Policy frame-ancestors` e applicando impostazioni mirate (non indebolire tutto il pannello admin).
- Validazione server-side, limiti lunghezza, normalizzazione essenziale del telefono, `source_url` validato, protezione da XSS.
- **Rate limiting** ragionevole per IP/endpoint e piccolo **honeypot** anti-bot, senza captcha o soluzioni complesse in V1. Duplicati con stessi dati permessi.
- Verificare **ogni invio** contro token offerta e stato attivo **corrente** sul server; nessun prezzo proveniente dal client.
- Generare token casuali e non prevedibili; endpoint pubblici non devono esporre dati gestionali o dettagli sugli ordini.
- Fornire una prova manuale concreta: creare prodotto/offerta da Filament, copiare script, incorporarlo in una pagina HTML separata e verificare l'ordine entrato nella tabella Filament con `source_url` corretto.

---

## 9. Filament 5 — risorse da consegnare

Nel singolo pannello `/admin`, realizzare:

- **Users** (solo admin): tabella, creazione/modifica, ruoli `admin` e `call_center`, attivo/disattivo; password gestita con hash e senza mostrare password esistente.
- **Products** (solo admin): tabella, form, foto, prezzi e stato.
- **Offers** (solo admin): tabella, form, stato, prezzo effettivo, copia script pubblicabile.
- **Orders** (admin e call center): tabella, filtri, dettaglio, Prendi in carico, edit dati/quantità, Conferma, Annulla, nei limiti dei permessi.

Menu, titoli, label, notifiche e messaggi **in italiano**. UI pulita e responsive. Evitare pagine vuote, navigazioni rotte, pulsanti non funzionanti e azioni presenti quando non consentite. Niente dashboard analitica complessa: quella predefinita o un riepilogo molto semplice è sufficiente.

---

## 10. Test e verifica di accettazione

Implementare test automatizzati utili (feature/unit, preferibilmente PHPUnit o Pest già presenti: non cambiare stack test inutilmente):

1. Il seeder crea solo i ruoli e le **due utenze demo**; `products`, `offers`, `orders` restano vuote. Riesecuzione idempotente.
2. `admin` accede a tutte le risorse, `call_center` soltanto agli ordini, incluso il divieto di accesso tramite URL e action.
3. Utente `is_active = false` non accede.
4. Prodotti con SKU duplicato o prezzi invalidi vengono rifiutati.
5. Offerta senza `custom_price` usa prezzo prodotto, con `custom_price` usa quello impostato.
6. Lo script di offerta attiva funziona da una landing di test; il form crea ordine `nuovo` con offerta corretta, quantità null, prezzo snapshot e `source_url` della landing host.
7. Nome, cognome o telefono mancanti causano errore; indirizzo assente è ammesso.
8. Due invii con lo stesso telefono producono **due ordini**.
9. Offerta disattivata: blocco sia presentazione utile del form sia POST (anche se l'iframe era già aperto).
10. Prima presa in carico cambia stato/assegnazione; seconda operatrice non può prenderlo o modificarlo. Validare anche il caso concorrente con una verifica appropriata a MySQL, quando l'ambiente lo permette.
11. Conferma consentita soltanto all'assegnataria, con quantità >= 1 e indirizzo; prezzo non modificabile e totale corretto.
12. Ordini `confermato` e `annullato` restano in lettura per call center.
13. Form non autenticato non consente mass assignment di `status`, `assigned_to`, `unit_price`, `total_price`, `quantity` o altri campi riservati.
14. `source_url` non consente script/URL non validi e l'interfaccia esegue l'escape corretto dei valori.

Eseguire il formato (`vendor/bin/pint`), i test (`php artisan test`) e i comandi di controllo disponibili. Se non è possibile eseguire una verifica nell'ambiente, dichiararlo in modo preciso anziché affermare che è riuscita.

---

## 11. Ordine di esecuzione richiesto

1. **Ispezionare il repository esistente**: versione PHP, Laravel/Filament, provider, migrazioni attuali, `User`, `.env` senza divulgarne segreti, dipendenze, routes, setup Filament.
2. Scrivere una **brevissima checklist interna** e iniziare subito a implementare, senza fare altre domande su requisiti già definiti qui.
3. Installare e configurare Spatie Permission compatibile. Definire ruoli, permessi/Policies e accesso al pannello, migration additiva `users`.
4. Creare e applicare migration/model per `products`, `offers`, `orders`.
5. Implementare seed **solo utenti** e ruoli, con password da `.env` locale e istruzioni per eseguirlo; mantenere gli utenti preesistenti.
6. Costruire le Resource Filament 5 con autorizzazioni effettive.
7. Implementare le Actions/Services di ordine e la presa in carico atomica.
8. Realizzare script copiabile, iframe e form pubblico realmente funzionanti, inclusa origine URL e disattivazione offerta.
9. Eseguire migration, seed **solo quando le password demo sono disponibili** e test/quality checks; non eseguire comandi distruttivi.
10. Fornire all'utente un **resoconto finale** con: file/componenti principali creati, comandi effettuati, credenziali da impostare (mai stampare password), percorso pannello, modalità di test dell'iframe, esito verifiche ed eventuali blocchi residui.

### Criterio di completamento

Il task è completato soltanto quando, con i due utenti demo, un admin può creare un prodotto, creare un'offerta, copiare uno script da incollare su una landing esterna, ricevere un ordine reale da quel form, e un'operatrice `call_center` può prenderlo in carico, lavorarlo e confermarlo o annullarlo rispettando permessi e concorrenza. **Nessuna spedizione o pagamento nella V1.**

**Implementa tutto ciò direttamente nel progetto. Non produrre soltanto un piano, una bozza, un prompt alternativo o codice non applicato.**
