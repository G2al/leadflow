# LeadFlow

Gestionale per acquisire richieste da landing page esterne e lavorarle manualmente tramite call center.
Stack: Laravel 12, Filament 5, Spatie Laravel Permission, MySQL/MariaDB. Il pannello è su `/admin`.

## Avvio

1. Nel file `.env` locale imposta `APP_URL` e, se disponibile, `LEADFLOW_PRIVACY_URL`.

2. Esegui migration e seed, poi avvia il server. Il seed crea i due utenti con password `password`: **a ogni esecuzione la reimposta**, anche se è stata cambiata, quindi non rilanciarlo dopo aver scelto una password definitiva.

   ```bash
   php artisan migrate
   php artisan db:seed
   php artisan storage:link   # solo se public/storage non esiste
   php artisan serve
   ```

| Ruolo | Email | Password |
|---|---|---|
| `admin` | `admin@leadflow.it` | `password` |
| `call_center` | `giulia.rossi@leadflow.it` | `password` |

Per dare un ruolo a un account già esistente:

```bash
php artisan leadflow:assign-role utente@esempio.it admin
```

## Prova manuale del flusso

1. Accedi come admin e crea un prodotto e un'offerta.
2. Per una prova rapida, nel dettaglio dell'offerta premi **Lancia script**: si apre una pagina interna che esegue lo script. Ogni invio da quella pagina crea un ordine reale.
3. Per provare una landing esterna premi **Copia script**, incolla il codice in una pagina HTML in un'altra cartella e servila su un'altra origine:

   ```bash
   php -S 127.0.0.1:9000 -t percorso/della/landing
   ```

4. Apri la pagina e invia il modulo: l'ordine compare in **Ordini** con stato `Nuovo` e URL della landing (senza query string).
5. Accedi come `giulia.rossi@leadflow.it` e premi **Prendi in carico**. Con **Conferma** si apre una finestra in cui inserire quantità e indirizzo: il totale si aggiorna da solo. In alternativa usa **Annulla ordine**.
6. Disattiva l'offerta: la landing mostra un avviso, e un modulo già aperto riceve un rifiuto all'invio.

La pagina va aperta via `http://`: da `file://` il modulo funziona, ma l'origine dell'ordine risulta non disponibile.

## Aggiornamenti in tempo reale

Tabella e dettaglio ordine si aggiornano da soli tramite WebSocket con Laravel Reverb. Il messaggio contiene solo l'ID dell'ordine sul canale privato `orders`: i dati si ricaricano con le normali richieste autorizzate. In locale avvia il server con:

```bash
php artisan reverb:start
```

Serve `BROADCAST_CONNECTION=reverb` con le variabili `REVERB_*` nel `.env`. Se Reverb è fermo gli ordini si salvano comunque e il pannello si aggiorna con un polling ogni 60 secondi.

## Architettura

| Percorso | Contenuto |
|---|---|
| `app/Actions/Orders` | Unica implementazione delle operazioni: creazione da landing, presa in carico, assegnazione, modifica, conferma, annullamento |
| `app/Policies` | Regole di autorizzazione. `OrderPolicy` viene rivalutata dalle Actions sulla riga bloccata |
| `app/Services` | `OrderLocker` (transazione con `lockForUpdate`), normalizzazione di telefono e URL, codice di incorporamento |
| `app/Filament/Resources` | Utenti, Prodotti, Offerte, Ordini. `Orders/OrderActions.php` collega UI e Actions |
| `routes/embed.php` | Rotte pubbliche stateless: `GET {token}.js`, `GET {token}/form`, `POST {token}/orders` |
| `app/Http/Middleware` | `EmbedFormHeaders` consente l'iframe solo al modulo; `DenyFraming` protegge pannello e sito |

Le rotte embed stanno fuori dal gruppo `web`: niente sessione, cookie o CSRF. Sono protette da token casuale, verifica dello stato dell'offerta a ogni invio, rate limiting per IP (10 invii al minuto, 60 all'ora) e honeypot.

Dietro un proxy o un load balancer va configurato `trustProxies`, altrimenti il rate limiting vede un solo IP.

## Test

```bash
vendor/bin/pint
php artisan test
```

Il test di concorrenza fa partire più processi sullo stesso ordine e richiede MySQL. Viene saltato se non indichi un database dedicato, che viene creato se manca:

```bash
LEADFLOW_CONCURRENCY_DB=leadflow_test php artisan test --filter=OrderConcurrencyTest
```
