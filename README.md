# GestMedicert ERP Santé — Version 1

GestMedicert-Commercial est la nouvelle application Laravel de gestion de santé au travail. Elle reprend progressivement les données du GestMedicert historique sans écrire dans sa base.

## Périmètre disponible

- Organisation, sites, directions, services et équipes configurables.
- Utilisateurs, rôles, permissions et périmètres par site.
- Patients employés, prestataires, stagiaires et ayants droit.
- Matricules et affectations professionnelles séparés de l’identité patient.
- Rendez-vous, arrivées spontanées et file d’attente.
- Consultations, constantes, diagnostics CIM-10, allergies et addendums.
- Prescriptions médicales et examens externes.
- Certificats avec soumission, validation, rejet, révision, révocation, PDF, SHA-256 et QR.
- Catalogue des médicaments et consommables.
- Magasins par site, lots, péremptions, quarantaine, entrées, transferts, ajustements et retours.
- Délivrances partielles et suivi des reliquats d’ordonnance.
- Tableaux de bord et rapports opérationnels.
- Audit applicatif chaîné par empreintes.
- Import idempotent de la base historique.

## Stack locale

- PHP 8.2 sous XAMPP
- Apache 2.4
- Laravel 12
- Inertia 2, Vue 3 et TypeScript
- SQL Server via `pdo_sqlsrv`
- Données métier dans SQL Server ; sessions/cache sur fichiers et queue synchrone pour le poste XAMPP local

Laravel 12 est volontairement utilisé pour rester compatible avec le PHP 8.2 actuellement installé dans XAMPP. Le passage à Laravel 13 nécessitera PHP 8.3 ou supérieur.

## Accès local préparé

Apache est configuré pour servir directement le répertoire `public` :

```text
http://127.0.0.1:8088
```

Le virtual host correspondant se trouve dans :

```text
C:\xampp\apache\conf\extra\httpd-vhosts.conf
```

Si Apache est arrêté, le redémarrer depuis le panneau XAMPP. Le port 8088 doit être libre.

## Compte de démonstration

```text
E-mail       : admin@gestmedicert.local
Mot de passe : ChangeMe123!
```

Ce compte est réservé au développement local. Changer son mot de passe avant toute démonstration externe.

Autres comptes de démonstration, avec le même mot de passe initial :

```text
medecin@gestmedicert.local
infirmier@gestmedicert.local
pharmacie@gestmedicert.local
rh@gestmedicert.local
```

Ils permettent notamment de vérifier la séparation entre données cliniques et données RH.

## Installation sur une nouvelle machine locale

### Prérequis

1. XAMPP avec PHP 8.2.
2. Extensions PHP `fileinfo`, `gd`, `mbstring`, `openssl`, `pdo_sqlsrv`, `sqlsrv` et `zip`.
3. Microsoft ODBC Driver for SQL Server.
4. Composer 2.
5. Node.js et npm.
6. Une instance SQL Server accessible.

### Dépendances

Depuis `C:\xampp\htdocs\GestMedicert-Commercial` :

```powershell
C:\xampp\php\php.exe C:\ProgramData\ComposerSetup\bin\composer.phar install
& "C:\Program Files\nodejs\npm.cmd" install
```

### Environnement

```powershell
Copy-Item .env.example .env
C:\xampp\php\php.exe artisan key:generate
```

Renseigner ensuite dans `.env` :

```dotenv
DB_CONNECTION=sqlsrv
DB_HOST=lpc:.\GESTMEDICERT
DB_PORT=
DB_DATABASE=GestMedicertCommercial
DB_USERNAME=...
DB_PASSWORD=...
DB_POOLING=false
DB_MARS=false
DB_LOGIN_TIMEOUT=30
DB_ENCRYPT=yes
DB_TRUST_SERVER_CERTIFICATE=true
```

Le préfixe `lpc:` force Shared Memory pour une instance SQL située sur la même machine et évite les délais de détection TCP/Named Pipes. Pour un serveur SQL distant, utiliser son nom ou son adresse TCP à la place.

Les secrets restent exclusivement dans `.env`, qui est ignoré par Git.

### Création du schéma et des données de démonstration

Créer préalablement une base vide nommée `GestMedicertCommercial`, puis exécuter :

```powershell
C:\xampp\php\php.exe artisan migrate --seed
& "C:\Program Files\nodejs\npm.cmd" run build
```

Sur SQL Server Express, désactiver `AUTO_CLOSE` pour éviter les délais entre Apache, Artisan et les workers :

```sql
ALTER DATABASE [GestMedicertCommercial] SET AUTO_CLOSE OFF;
```

Ne jamais lancer `migrate:fresh` sur une base contenant des données à conserver.

## Reprise du GestMedicert historique

Configurer la connexion source `LEGACY_DB_*` dans `.env`. Cette connexion est distincte de la base cible.

Contrôle sans écriture :

```powershell
C:\xampp\php\php.exe artisan gestmedicert:import-legacy --dry-run
```

Import :

```powershell
C:\xampp\php\php.exe artisan gestmedicert:import-legacy
```

L’import :

- crée des correspondances source/cible ;
- peut être rejoué ;
- ne modifie jamais les tables historiques ;
- force le changement de mot de passe des comptes repris ;
- archive les anciens journaux sans les présenter comme des événements natifs ;
- copie et empreinte les signatures disponibles ;
- produit un rapport dans `legacy_import_runs`.

Les anciens employés ne possèdent pas de date de naissance ni de sexe dans la base source. Ces informations doivent être complétées dans le nouveau dossier patient.

## Commandes de développement

Le serveur HTTP est déjà fourni par Apache. Pour modifier le front en direct :

```powershell
& "C:\Program Files\nodejs\npm.cmd" run dev
```

Pour traiter les futures tâches asynchrones :

```powershell
C:\xampp\php\php.exe artisan queue:work --tries=3 --timeout=120
```

## Validation

```powershell
C:\xampp\php\php.exe vendor\bin\pint --test
C:\xampp\php\php.exe artisan test
& "C:\Program Files\nodejs\npm.cmd" run build
C:\xampp\php\php.exe artisan migrate:status
```

Les tests automatisés couvrent notamment :

- authentification ;
- absence d’inscription publique ;
- confidentialité des documents pour RH/HSE ;
- détection d’altération de la chaîne d’audit ;
- blocage des stocks négatifs ;
- retour des médicaments en quarantaine.

## Données sensibles

- Les fichiers médicaux et PDF sont stockés sous `storage/app/private`.
- Le répertoire web public est `public`, jamais la racine du projet.
- Les administrateurs techniques ne reçoivent pas automatiquement les données cliniques.
- Les profils RH/HSE ne reçoivent pas les diagnostics, notes cliniques ou ordonnances.
- Les sauvegardes de SQL Server et de `storage/app/private` doivent être réalisées ensemble.

## Limites de cette V1

Cette version n’est pas encore un HIS hospitalier complet. Sont reportés :

- hospitalisation et gestion des lits ;
- laboratoire interne complet et automates ;
- PACS/imagerie DICOM ;
- facturation, assurances et comptabilité ;
- campagnes de médecine du travail ;
- Active Directory et MFA ;
- API FHIR/HL7 ;
- installateur, licence et mises à jour hors ligne industrialisées.

Ces évolutions sont prévues derrière les modules actuels, sans imposer une réécriture du cœur Laravel.
