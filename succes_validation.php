<?php
// filepath: c:\wamp\www\decaissement\succes_validation.php
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Succès !</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        .success-anim {
            animation: pop 0.7s cubic-bezier(.36, 1.64, .62, .99);
        }

        @keyframes pop {
            0% {
                transform: scale(0.5);
                opacity: 0;
            }

            80% {
                transform: scale(1.1);
                opacity: 1;
            }

            100% {
                transform: scale(1);
            }
        }

        .confetti {
            position: absolute;
            width: 100vw;
            height: 100vh;
            pointer-events: none;
            z-index: 10;
        }
    </style>
</head>

<body class="bg-gradient-to-br from-yellow-100 to-yellow-300 min-h-screen flex items-center justify-center relative overflow-hidden">
    <!-- Confetti SVG -->
    <canvas class="confetti" id="confetti"></canvas>
    <div class="bg-white rounded-3xl shadow-2xl p-8 max-w-xs w-full flex flex-col items-center success-anim">
        <div class="bg-green-100 rounded-full p-4 mb-4 shadow-lg">
            <i class="fa-solid fa-circle-check text-green-500 text-6xl"></i>
        </div>
        <h1 class="text-2xl font-extrabold text-yellow-700 mb-2 text-center">Validation réussie !</h1>
        <p class="text-gray-700 text-center mb-4">
            Votre fiche de réparation a été validée avec succès.<br>
            Merci pour votre confiance.<br>
            <span class="text-green-600 font-bold">✔️</span>
        </p>
        <a href="index.php" class="mt-2 inline-block bg-yellow-400 hover:bg-yellow-500 text-black font-bold py-2 px-6 rounded-full shadow transition">Retour à l'accueil</a>
    </div>
    <script>
        // Confetti simple
        const canvas = document.getElementById('confetti');
        const ctx = canvas.getContext('2d');
        let W = window.innerWidth,
            H = window.innerHeight;
        canvas.width = W;
        canvas.height = H;
        let confetti = [];
        for (let i = 0; i < 80; i++) {
            confetti.push({
                x: Math.random() * W,
                y: Math.random() * H - 50,
                r: Math.random() * 6 + 4,
                d: Math.random() * 80 + 40,
                color: ["#FACC15", "#FDE68A", "#34D399", "#F87171", "#60A5FA"][Math.floor(Math.random() * 5)],
                tilt: Math.floor(Math.random() * 10) - 10
            });
        }

        function draw() {
            ctx.clearRect(0, 0, W, H);
            for (let i = 0; i < confetti.length; i++) {
                let c = confetti[i];
                ctx.beginPath();
                ctx.lineWidth = c.r;
                ctx.strokeStyle = c.color;
                ctx.moveTo(c.x + c.tilt + c.r / 3, c.y);
                ctx.lineTo(c.x + c.tilt, c.y + c.d / 10);
                ctx.stroke();
            }
            update();
        }

        function update() {
            for (let i = 0; i < confetti.length; i++) {
                let c = confetti[i];
                c.y += Math.cos(c.d) + 1 + c.r / 2;
                c.x += Math.sin(0);
                if (c.y > H) {
                    confetti[i] = {
                        x: Math.random() * W,
                        y: -10,
                        r: c.r,
                        d: c.d,
                        color: c.color,
                        tilt: c.tilt
                    };
                }
            }
        }
        setInterval(draw, 20);
    </script>
</body>

</html>