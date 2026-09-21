import sys
import os
import pandas as pd
import mysql.connector
from mysql.connector import Error

# CONFIGURATION DE LA CONNEXION MYSQL
# Mêmes valeurs que dans config.php à la racine du projet (XAMPP : root sans
# mot de passe). Chaque valeur peut être surchargée par une variable
# d'environnement sans modifier ce fichier, ex. sous PowerShell :
#     $env:IRVE_DB_USER = 'tv_fowet'; $env:IRVE_DB_PASS = '...'; py import_fichier_csv.py
db_config = {
    'host':     os.environ.get('IRVE_DB_HOST', '127.0.0.1'),
    'port':     int(os.environ.get('IRVE_DB_PORT', '3306')),
    'user':     os.environ.get('IRVE_DB_USER', 'root'),
    'password': os.environ.get('IRVE_DB_PASS', ''),
}
DB_NAME = os.environ.get('IRVE_DB_NAME', 'tv_fowet')

# Sous Windows, la console n'est pas toujours en UTF-8 : évite un plantage
# sur les emojis/accents des messages ci-dessous.
if hasattr(sys.stdout, "reconfigure"):
    sys.stdout.reconfigure(encoding="utf-8", errors="replace")

# CHEMIN VERS LE CSV (meme dossier que ce script)
CSV_PATH = os.path.join(os.path.dirname(__file__), 'export_IA.csv')


# ══════════════════════════════════════════════════
#  UTILITAIRES
# ══════════════════════════════════════════════════

def extraire_dept(cp):
    c = str(cp).split('.')[0].strip().zfill(5)
    return c[:3] if c[:2] in ('97', '98') else c[:2]

def esc(v, maxlen=None):
    if v is None or (isinstance(v, float) and pd.isna(v)):
        return None
    s = str(v).strip()
    if s in ('', 'nan', 'None'):
        return None
    return s[:maxlen] if maxlen else s

NOMS_DEPT = {
    '01': 'Ain', '02': 'Aisne', '03': 'Allier', '04': 'Alpes-de-Haute-Provence',
    '05': 'Hautes-Alpes', '06': 'Alpes-Maritimes', '07': 'Ardèche', '08': 'Ardennes',
    '09': 'Ariège', '10': 'Aube', '11': 'Aude', '12': 'Aveyron', '13': 'Bouches-du-Rhône',
    '14': 'Calvados', '15': 'Cantal', '16': 'Charente', '17': 'Charente-Maritime',
    '18': 'Cher', '19': 'Corrèze', '21': "Côte-d'Or", '22': "Côtes-d'Armor",
    '23': 'Creuse', '24': 'Dordogne', '25': 'Doubs', '26': 'Drôme', '27': 'Eure',
    '28': 'Eure-et-Loir', '29': 'Finistère', '2A': 'Corse-du-Sud', '2B': 'Haute-Corse',
    '30': 'Gard', '31': 'Haute-Garonne', '32': 'Gers', '33': 'Gironde', '34': 'Hérault',
    '35': 'Ille-et-Vilaine', '36': 'Indre', '37': 'Indre-et-Loire', '38': 'Isère',
    '39': 'Jura', '40': 'Landes', '41': 'Loir-et-Cher', '42': 'Loire',
    '43': 'Haute-Loire', '44': 'Loire-Atlantique', '45': 'Loiret', '46': 'Lot',
    '47': 'Lot-et-Garonne', '48': 'Lozère', '49': 'Maine-et-Loire', '50': 'Manche',
    '51': 'Marne', '52': 'Haute-Marne', '53': 'Mayenne', '54': 'Meurthe-et-Moselle',
    '55': 'Meuse', '56': 'Morbihan', '57': 'Moselle', '58': 'Nièvre', '59': 'Nord',
    '60': 'Oise', '61': 'Orne', '62': 'Pas-de-Calais', '63': 'Puy-de-Dôme',
    '64': 'Pyrénées-Atlantiques', '65': 'Hautes-Pyrénées', '66': 'Pyrénées-Orientales',
    '67': 'Bas-Rhin', '68': 'Haut-Rhin', '69': 'Rhône', '70': 'Haute-Saône',
    '71': 'Saône-et-Loire', '72': 'Sarthe', '73': 'Savoie', '74': 'Haute-Savoie',
    '75': 'Paris', '76': 'Seine-Maritime', '77': 'Seine-et-Marne', '78': 'Yvelines',
    '79': 'Deux-Sèvres', '80': 'Somme', '81': 'Tarn', '82': 'Tarn-et-Garonne',
    '83': 'Var', '84': 'Vaucluse', '85': 'Vendée', '86': 'Vienne', '87': 'Haute-Vienne',
    '88': 'Vosges', '89': 'Yonne', '90': 'Territoire de Belfort', '91': 'Essonne',
    '92': 'Hauts-de-Seine', '93': 'Seine-Saint-Denis', '94': 'Val-de-Marne',
    '95': "Val-d'Oise", '971': 'Guadeloupe', '972': 'Martinique', '973': 'Guyane',
    '974': 'La Réunion', '976': 'Mayotte',
}


# ══════════════════════════════════════════════════
#  FONCTION PRINCIPALE
# ══════════════════════════════════════════════════

def importer_base():
    # En-tête HTTP uniquement si le script est lancé comme CGI depuis un navigateur
    if 'GATEWAY_INTERFACE' in os.environ:
        print("Content-Type: text/plain; charset=utf-8")
        print()  # Ligne vide obligatoire !

    conn = None
    try:
        # CHARGEMENT DU CSV
        print("📂 Chargement du fichier export_IA.csv...")
        df = pd.read_csv(CSV_PATH, sep=None, engine='python', dtype=str)
        for col in ['consolidated_latitude', 'consolidated_longitude', 'puissance_nominale']:
            df[col] = pd.to_numeric(df[col], errors='coerce')
        for col in ['prise_type_2', 'prise_type_combo_ccs', 'prise_type_chademo', 'paiement_acte', 'reservation']:
            df[col] = df[col].map(lambda v: 1 if str(v).strip().lower() in ('true', '1') else 0)
        df['nbre_pdc'] = pd.to_numeric(df['nbre_pdc'], errors='coerce').fillna(1).astype(int)
        df = df.dropna(subset=['consolidated_latitude', 'consolidated_longitude'])
        df['_code_dept'] = df['consolidated_code_postal'].apply(extraire_dept)
        print(f"✅ {len(df)} lignes valides chargees !")

        # CONNEXION BDD
        print("\n🔄 Connexion au serveur MySQL en cours...")
        conn = mysql.connector.connect(**db_config)
        if conn.is_connected():
            cursor = conn.cursor()
            print(f"✅ Connexion reussie a MySQL ({db_config['host']}:{db_config['port']}) !")

            # CREATION DE LA BASE SI ELLE N'EXISTE PAS (installation locale XAMPP)
            cursor.execute(f"CREATE DATABASE IF NOT EXISTS `{DB_NAME}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;")
            cursor.execute(f"USE `{DB_NAME}`;")
            print(f"✅ Base de donnees '{DB_NAME}' prete !")

            cursor.execute("SET FOREIGN_KEY_CHECKS = 0;")

            # SUPPRESSION DES ANCIENNES TABLES (pour repartir proprement)
            print("\n🗑️ Suppression des anciennes tables si elles existent...")
            cursor.execute("DROP TABLE IF EXISTS POINT_DE_CHARGE;")
            cursor.execute("DROP TABLE IF EXISTS STATION;")
            cursor.execute("DROP TABLE IF EXISTS DEPARTEMENT;")
            print("✅ Anciennes tables supprimees !")

            # 1. TABLE DEPARTEMENT
            print("\n🛠️ Creation de la table DEPARTEMENT...")
            cursor.execute("""
                CREATE TABLE IF NOT EXISTS DEPARTEMENT (
                    id_dept   INT AUTO_INCREMENT,
                    code_dept VARCHAR(3)   NOT NULL UNIQUE,
                    nom_dept  VARCHAR(100) NOT NULL DEFAULT '',
                    PRIMARY KEY (id_dept)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            """)

            # 2. TABLE STATION
            print("🛠️ Creation de la table STATION...")
            cursor.execute("""
                CREATE TABLE IF NOT EXISTS STATION (
                    id_station       INT AUTO_INCREMENT,
                    nom_enseigne     VARCHAR(100),
                    adresse_station  VARCHAR(255),
                    latitude         DECIMAL(10,8),
                    longitude        DECIMAL(11,8),
                    code_departement VARCHAR(5),
                    id_dept          INT NOT NULL,
                    PRIMARY KEY (id_station),
                    CONSTRAINT fk_station_dept FOREIGN KEY (id_dept)
                        REFERENCES DEPARTEMENT(id_dept) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            """)

            # 3. TABLE POINT_DE_CHARGE
            print("🛠️ Creation de la table POINT_DE_CHARGE...")
            cursor.execute("""
                CREATE TABLE IF NOT EXISTS POINT_DE_CHARGE (
                    id_pdc               INT AUTO_INCREMENT,
                    nbre_pdc             INT,
                    puissance_nominale   FLOAT,
                    prise_type_2         BOOLEAN,
                    prise_type_combo_ccs BOOLEAN,
                    prise_type_chademo   BOOLEAN,
                    paiement_acte        BOOLEAN,
                    condition_acces      VARCHAR(50),
                    reservation          BOOLEAN,
                    accessibilite_pmr    VARCHAR(50),
                    restriction_gabarit  VARCHAR(50),
                    horaires             VARCHAR(100),
                    id_station           INT NOT NULL,
                    PRIMARY KEY (id_pdc),
                    CONSTRAINT fk_pdc_station FOREIGN KEY (id_station)
                        REFERENCES STATION(id_station) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            """)

            # 4. VUE POUR L'IA
            print("🔮 Creation de la VUE virtuelle pour le modele d'IA...")
            cursor.execute("""
                CREATE OR REPLACE VIEW vue_dataset_ia AS
                SELECT
                    p.id_pdc,
                    p.puissance_nominale,
                    p.nbre_pdc,
                    IF(p.prise_type_2 = 1, 1, 0)        AS has_type_2,
                    IF(p.prise_type_combo_ccs = 1, 1, 0) AS has_combo_ccs,
                    IF(p.prise_type_chademo = 1, 1, 0)   AS has_chademo,
                    IF(p.paiement_acte = 1, 1, 0)        AS paiement_acte,
                    IF(p.reservation = 1, 1, 0)          AS reservation_possible,
                    s.latitude,
                    s.longitude,
                    d.code_dept
                FROM POINT_DE_CHARGE p
                JOIN STATION     s ON p.id_station = s.id_station
                JOIN DEPARTEMENT d ON s.id_dept    = d.id_dept;
            """)

            # 4bis. TABLES D'HISTORIQUE DES PREDICTIONS (fonctionnalite 5)
            #       Non supprimees a chaque import : on conserve l'historique.
            print("🧠 Creation des tables PREDICTION_IMPLANTATION / PREDICTION_PUISSANCE...")
            cursor.execute("""
                CREATE TABLE IF NOT EXISTS PREDICTION_IMPLANTATION (
                    id_prediction   INT AUTO_INCREMENT,
                    random_forest   VARCHAR(100),
                    prediction_rf   VARCHAR(100),
                    accuracy_rf     FLOAT,
                    svm             VARCHAR(100),
                    prediction_svm  VARCHAR(100),
                    accuracy_svm    FLOAT,
                    date_prediction DATETIME,
                    PRIMARY KEY (id_prediction)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            """)
            cursor.execute("""
                CREATE TABLE IF NOT EXISTS PREDICTION_PUISSANCE (
                    id_prediction   INT AUTO_INCREMENT,
                    random_forest   FLOAT,
                    prediction_rf   FLOAT,
                    accuracy_rf     FLOAT,
                    svm             FLOAT,
                    accuracy_svm    FLOAT,
                    erreur_modele   FLOAT,
                    date_prediction DATETIME,
                    PRIMARY KEY (id_prediction)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            """)

            conn.commit()
            print("✅ Tables et vue creees avec succes !")

            # 5. IMPORT DEPARTEMENTS
            print("\n📍 Import des DEPARTEMENTS...")
            dept_id_map = {}
            for code in sorted(df['_code_dept'].dropna().unique()):
                nom = NOMS_DEPT.get(str(code), f'Departement {code}')
                cursor.execute("""
                    INSERT INTO DEPARTEMENT (code_dept, nom_dept)
                    VALUES (%s, %s)
                    ON DUPLICATE KEY UPDATE nom_dept = VALUES(nom_dept)
                """, (code, nom))
            conn.commit()
            cursor.execute("SELECT id_dept, code_dept FROM DEPARTEMENT")
            for id_d, code_d in cursor.fetchall():
                dept_id_map[code_d] = id_d
            print(f"✅ {len(dept_id_map)} departements importes !")

            # 6. IMPORT STATIONS
            print("\n📡 Import des STATIONS...")
            stations_df    = df.drop_duplicates(subset='id_station_itinerance').copy()
            station_lookup = {}
            sta_id         = 1
            batch          = []

            for _, row in stations_df.iterrows():
                id_dept = dept_id_map.get(row['_code_dept'])
                if id_dept is None:
                    continue
                lat = row['consolidated_latitude']
                lon = row['consolidated_longitude']
                cp  = str(row.get('consolidated_code_postal', '')).split('.')[0].zfill(5)[:5]
                batch.append((
                    esc(row.get('nom_enseigne'),    100),
                    esc(row.get('adresse_station'), 255),
                    float(lat) if pd.notna(lat) else None,
                    float(lon) if pd.notna(lon) else None,
                    cp, id_dept,
                ))
                if pd.notna(lat) and pd.notna(lon):
                    station_lookup[(round(float(lat), 5), round(float(lon), 5))] = sta_id
                sta_id += 1

                if len(batch) >= 200:
                    cursor.executemany("""
                        INSERT INTO STATION (nom_enseigne, adresse_station, latitude, longitude, code_departement, id_dept)
                        VALUES (%s, %s, %s, %s, %s, %s)
                    """, batch)
                    conn.commit()
                    batch.clear()

            if batch:
                cursor.executemany("""
                    INSERT INTO STATION (nom_enseigne, adresse_station, latitude, longitude, code_departement, id_dept)
                    VALUES (%s, %s, %s, %s, %s, %s)
                """, batch)
                conn.commit()

            print(f"✅ {len(station_lookup)} stations importees !")

            # 7. IMPORT POINTS DE CHARGE
            print("\n⚡ Import des POINTS_DE_CHARGE...")
            batch   = []
            nb_ok   = 0
            nb_skip = 0

            for _, row in df.iterrows():
                lat    = row['consolidated_latitude']
                lon    = row['consolidated_longitude']
                id_sta = station_lookup.get((round(float(lat), 5), round(float(lon), 5))) if pd.notna(lat) and pd.notna(lon) else None
                if id_sta is None:
                    nb_skip += 1
                    continue

                batch.append((
                    int(row['nbre_pdc']),
                    float(row['puissance_nominale']) if pd.notna(row['puissance_nominale']) else None,
                    int(row['prise_type_2']),
                    int(row['prise_type_combo_ccs']),
                    int(row['prise_type_chademo']),
                    int(row['paiement_acte']),
                    esc(row.get('condition_acces'),     50),
                    int(row['reservation']),
                    esc(row.get('accessibilite_pmr'),   50),
                    esc(row.get('restriction_gabarit'), 50),
                    esc(row.get('horaires'),            100),
                    id_sta,
                ))
                nb_ok += 1

                if len(batch) >= 200:
                    cursor.executemany("""
                        INSERT INTO POINT_DE_CHARGE
                            (nbre_pdc, puissance_nominale, prise_type_2, prise_type_combo_ccs,
                             prise_type_chademo, paiement_acte, condition_acces, reservation,
                             accessibilite_pmr, restriction_gabarit, horaires, id_station)
                        VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
                    """, batch)
                    conn.commit()
                    batch.clear()

            if batch:
                cursor.executemany("""
                    INSERT INTO POINT_DE_CHARGE
                        (nbre_pdc, puissance_nominale, prise_type_2, prise_type_combo_ccs,
                         prise_type_chademo, paiement_acte, condition_acces, reservation,
                         accessibilite_pmr, restriction_gabarit, horaires, id_station)
                    VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
                """, batch)
                conn.commit()

            print(f"✅ {nb_ok} points de charge importes ({nb_skip} ignores) !")

            cursor.execute("SET FOREIGN_KEY_CHECKS = 1;")
            conn.commit()

            print("\n🚀 [SUCCES] La base de donnees a ete importee dans MySQL !")

    except Error as e:
        print(f"\n❌ Erreur MySQL : {e}")

    except Exception as e:
        print(f"\n❌ Erreur : {e}")

    finally:
        if conn and conn.is_connected():
            cursor.close()
            conn.close()
            print("🔌 Connexion MySQL fermee.")


if __name__ == "__main__":
    importer_base()