## 🌍 Orizon Travel Agency
Orizon è una web application progettata per la gestione di viaggi e destinazioni turistiche. Realizzata come progetto accademico, ha l'obiettivo di fornire un'interfaccia CRUD (Create, Read, Update, Delete) per gestire Paesi e Viaggi collegati, utilizzando PHP (con PDO), MySQL e Fetch API via JavaScript per comunicare tra frontend e backend.

# 🚀 Funzionalità Principali
Gestione Paesi: Aggiungi, modifica o elimina paesi dal database.

Gestione Viaggi: Associa viaggi ai paesi, includendo nome, descrizione, durata, prezzo.

Interfaccia dinamica: Il frontend aggiorna automaticamente le liste di paesi e viaggi senza ricaricare la pagina.

Comunicazione asincrona: Tutte le operazioni sono gestite tramite Fetch API e risposte JSON.

Validazione server-side: I dati ricevuti vengono sempre validati prima di essere inseriti nel database.

Organizzazione modulare del backend: Codice backend suddiviso in file PHP distinti per responsabilità (paesi, viaggi, connessione, ecc.).

# 🧠 Tecnologie Utilizzate
Tecnologia	Ruolo
HTML/CSS/JS	Struttura e interattività del frontend
PHP (PDO)	Backend e gestione sicura del database
MySQL (via MAMP)	Database relazionale
Fetch API	Comunicazione asincrona tra client e server
dotenv (vlucas/phpdotenv)	Gestione sicura delle variabili d’ambiente
