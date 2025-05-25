<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>Test Insert Fiche Carburant</title>
    <style>
        body {
            background: linear-gradient(120deg, #2980b9, #6dd5fa, #ffffff);
            min-height: 100vh;
            margin: 0;
            font-family: 'Segoe UI', Arial, sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .container {
            background: #fff;
            padding: 2.5rem 2rem 2rem 2rem;
            border-radius: 18px;
            box-shadow: 0 8px 32px 0 rgba(44, 62, 80, 0.18);
            max-width: 400px;
            width: 100%;
            text-align: center;
        }

        h2 {
            color: #2980b9;
            margin-bottom: 1.5rem;
            letter-spacing: 1px;
        }

        label {
            display: block;
            margin-bottom: 0.5rem;
            color: #34495e;
            font-weight: 500;
            text-align: left;
        }

        input[type="text"],
        input[type="number"] {
            width: 100%;
            padding: 0.7rem;
            margin-bottom: 1.2rem;
            border: 1px solid #b2bec3;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.2s;
        }

        input[type="text"]:focus,
        input[type="number"]:focus {
            border-color: #2980b9;
            outline: none;
        }

        button {
            background: linear-gradient(90deg, #2980b9, #6dd5fa);
            color: #fff;
            border: none;
            padding: 0.9rem 2.2rem;
            border-radius: 8px;
            font-size: 1.1rem;
            font-weight: bold;
            cursor: pointer;
            box-shadow: 0 4px 16px rgba(52, 152, 219, 0.12);
            transition: background 0.2s, transform 0.2s;
        }

        button:hover {
            background: linear-gradient(90deg, #6dd5fa, #2980b9);
            transform: translateY(-2px) scale(1.03);
        }
    </style>
</head>

<body>
    <div class="container">
        <h2>🚗 Test fiche carburant</h2>
        <form action="request/insert_fiche.php" method="post">
            <label>Nom du demandeur :</label>
            <input type="text" name="nom_prenom" required>

            <label>Montant :</label>
            <input type="number" name="montant" required>

            <label>Téléphone du boss :</label>
            <input type="text" name="telephone_boss" required>

            <label>Téléphone du demandeur :</label>
            <input type="text" name="telephone" required>

            <!-- Champs cachés pour valeurs par défaut nécessaires à insert_fiche.php -->
            <input type="hidden" name="affectation" value="1">
            <input type="hidden" name="mode_paiement" value="Cash">
            <input type="hidden" name="chantier" value="1">
            <input type="hidden" name="details_demande" value="Demande de carburant test">
            <input type="hidden" name="motif_select" value="Carburant pour test">
            <input type="hidden" name="service" value="1">

            <button type="submit">🚀 Envoyer test</button>
        </form>
    </div>
</body>

</html>