<template>
    <svg
        class="h-auto w-full"
        viewBox="0 0 960 520"
        xmlns="http://www.w3.org/2000/svg"
        role="img"
        aria-label="Diagramme circulaire de la boucle d'affichage du téléviseur : Bienvenue, puis le planning du jour (matin, après-midi ou soir selon l'heure), puis les images, puis les vidéos, avant de revenir au début. Chaque diapositive possède une durée, automatique pour le planning et les vidéos."
        font-family="ui-sans-serif, system-ui, sans-serif"
    >
        <defs>
            <marker
                id="cycleSlidesArrow"
                viewBox="0 0 10 10"
                refX="8"
                refY="5"
                markerWidth="6"
                markerHeight="6"
                orient="auto-start-reverse"
            >
                <path d="M 0 0 L 10 5 L 0 10 Z" fill="#f2ae35" />
            </marker>
        </defs>

        <rect x="0" y="0" width="960" height="520" rx="20" fill="#f8fafc" />

        <text x="40" y="60" font-size="26" font-weight="700" fill="#1e2d55">
            La boucle d'affichage du téléviseur
        </text>
        <text x="40" y="90" font-size="15" fill="#71717a">
            Les diapositives s'enchaînent sans intervention, puis reprennent au
            début.
        </text>

        <circle
            cx="330"
            cy="300"
            r="140"
            fill="none"
            stroke="#e4e4e7"
            stroke-width="2"
            stroke-dasharray="6 8"
        />

        <g
            fill="none"
            stroke="#f2ae35"
            stroke-width="4"
            stroke-linecap="round"
            marker-end="url(#cycleSlidesArrow)"
        >
            <path d="M 404.2 181.3 A 140 140 0 0 1 448.7 225.8" />
            <path d="M 448.7 374.2 A 140 140 0 0 1 404.2 418.7" />
            <path d="M 255.8 418.7 A 140 140 0 0 1 211.3 374.2" />
            <path d="M 211.3 225.8 A 140 140 0 0 1 255.8 181.3" />
        </g>

        <text
            x="330"
            y="292"
            font-size="18"
            font-weight="700"
            fill="#1e2d55"
            text-anchor="middle"
        >
            Écran TV
        </text>
        <text
            x="330"
            y="316"
            font-size="14"
            fill="#71717a"
            text-anchor="middle"
        >
            boucle continue
        </text>

        <!-- 1. Bienvenue -->
        <g>
            <circle
                cx="330"
                cy="160"
                r="58"
                fill="#ffffff"
                stroke="#1e2d55"
                stroke-width="3"
            />
            <circle cx="330" cy="130" r="13" fill="#f2ae35" />
            <text
                x="330"
                y="135"
                font-size="13"
                font-weight="700"
                fill="#1e2d55"
                text-anchor="middle"
            >
                1
            </text>
            <text
                x="330"
                y="168"
                font-size="17"
                font-weight="700"
                fill="#1e2d55"
                text-anchor="middle"
            >
                Bienvenue
            </text>
            <text
                x="330"
                y="190"
                font-size="13"
                fill="#71717a"
                text-anchor="middle"
            >
                5 secondes
            </text>
        </g>

        <!-- 2. Planning -->
        <g>
            <circle
                cx="470"
                cy="300"
                r="58"
                fill="#ffffff"
                stroke="#1e2d55"
                stroke-width="3"
            />
            <circle cx="470" cy="270" r="13" fill="#f2ae35" />
            <text
                x="470"
                y="275"
                font-size="13"
                font-weight="700"
                fill="#1e2d55"
                text-anchor="middle"
            >
                2
            </text>
            <text
                x="470"
                y="308"
                font-size="17"
                font-weight="700"
                fill="#1e2d55"
                text-anchor="middle"
            >
                Planning
            </text>
            <text
                x="470"
                y="330"
                font-size="13"
                fill="#71717a"
                text-anchor="middle"
            >
                Auto
            </text>
        </g>

        <!-- 3. Images -->
        <g>
            <circle
                cx="330"
                cy="440"
                r="58"
                fill="#ffffff"
                stroke="#1e2d55"
                stroke-width="3"
            />
            <circle cx="330" cy="410" r="13" fill="#f2ae35" />
            <text
                x="330"
                y="415"
                font-size="13"
                font-weight="700"
                fill="#1e2d55"
                text-anchor="middle"
            >
                3
            </text>
            <text
                x="330"
                y="448"
                font-size="17"
                font-weight="700"
                fill="#1e2d55"
                text-anchor="middle"
            >
                Images
            </text>
            <text
                x="330"
                y="470"
                font-size="13"
                fill="#71717a"
                text-anchor="middle"
            >
                Durée réglable
            </text>
        </g>

        <!-- 4. Vidéos -->
        <g>
            <circle
                cx="190"
                cy="300"
                r="58"
                fill="#ffffff"
                stroke="#1e2d55"
                stroke-width="3"
            />
            <circle cx="190" cy="270" r="13" fill="#f2ae35" />
            <text
                x="190"
                y="275"
                font-size="13"
                font-weight="700"
                fill="#1e2d55"
                text-anchor="middle"
            >
                4
            </text>
            <text
                x="190"
                y="308"
                font-size="17"
                font-weight="700"
                fill="#1e2d55"
                text-anchor="middle"
            >
                Vidéos
            </text>
            <text
                x="190"
                y="330"
                font-size="13"
                fill="#71717a"
                text-anchor="middle"
            >
                Auto
            </text>
        </g>

        <!-- Légende des durées -->
        <rect
            x="560"
            y="140"
            width="360"
            height="330"
            rx="18"
            fill="#ffffff"
            stroke="#e4e4e7"
            stroke-width="2"
        />
        <text x="590" y="182" font-size="17" font-weight="700" fill="#1e2d55">
            Durée de chaque diapositive
        </text>

        <rect x="590" y="212" width="10" height="10" rx="3" fill="#f2ae35" />
        <text x="612" y="222" font-size="15" fill="#1e2d55">Bienvenue</text>
        <text x="890" y="222" font-size="15" fill="#71717a" text-anchor="end">
            5 s
        </text>

        <rect x="590" y="254" width="10" height="10" rx="3" fill="#f2ae35" />
        <text x="612" y="264" font-size="15" fill="#1e2d55">
            Planning du jour
        </text>
        <text x="890" y="264" font-size="15" fill="#71717a" text-anchor="end">
            Auto
        </text>

        <rect x="590" y="296" width="10" height="10" rx="3" fill="#f2ae35" />
        <text x="612" y="306" font-size="15" fill="#1e2d55">Images</text>
        <text x="890" y="306" font-size="15" fill="#71717a" text-anchor="end">
            Durée réglable
        </text>

        <rect x="590" y="338" width="10" height="10" rx="3" fill="#f2ae35" />
        <text x="612" y="348" font-size="15" fill="#1e2d55">Vidéos</text>
        <text x="890" y="348" font-size="15" fill="#71717a" text-anchor="end">
            Fin de la vidéo
        </text>

        <rect
            x="584"
            y="382"
            width="312"
            height="64"
            rx="12"
            fill="#f2ae35"
            fill-opacity="0.16"
        />
        <text x="606" y="410" font-size="14" fill="#1e2d55">
            Le planning bascule seul entre matin,
        </text>
        <text x="606" y="430" font-size="14" fill="#1e2d55">
            après-midi et soir selon l'heure.
        </text>
    </svg>
</template>
