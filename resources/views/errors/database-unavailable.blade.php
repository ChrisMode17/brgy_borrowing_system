<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Temporarily unavailable</title>
    <style>
        :root {
            color-scheme: light;
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            color: #172033;
            background: #f3f6fb;
            font-synthesis: none;
            text-rendering: optimizeLegibility;
            -webkit-font-smoothing: antialiased;
        }

        * {
            box-sizing: border-box;
        }

        body {
            display: grid;
            min-height: 100vh;
            margin: 0;
            padding: 24px;
            place-items: center;
            background:
                radial-gradient(ellipse at 50% 0%, rgba(219, 234, 254, 0.72), transparent 56%),
                #f3f6fb;
        }

        main {
            width: min(100%, 520px);
            padding: clamp(32px, 7vw, 56px);
            border: 1px solid #e2e8f0;
            border-radius: 24px;
            background: rgba(255, 255, 255, 0.96);
            box-shadow: 0 24px 70px rgba(15, 23, 42, 0.09);
            text-align: center;
        }

        .icon {
            display: grid;
            width: 68px;
            height: 68px;
            margin: 0 auto 28px;
            place-items: center;
            border: 1px solid #fed7aa;
            border-radius: 20px;
            background: #fff7ed;
            color: #c2410c;
        }

        .eyebrow {
            margin: 0 0 12px;
            color: #64748b;
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 0.14em;
            text-transform: uppercase;
        }

        h1 {
            margin: 0;
            color: #0f172a;
            font-size: clamp(1.8rem, 6vw, 2.25rem);
            line-height: 1.15;
            letter-spacing: -0.04em;
        }

        .message {
            margin: 18px 0 0;
            color: #475569;
            font-size: 1rem;
            line-height: 1.7;
        }

        .note {
            margin: 12px 0 0;
            color: #64748b;
            font-size: 0.875rem;
            line-height: 1.6;
        }

        button {
            min-height: 48px;
            margin-top: 30px;
            padding: 0 24px;
            border: 0;
            border-radius: 999px;
            background: #0f172a;
            color: #fff;
            font: inherit;
            font-size: 0.9375rem;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 150ms ease, transform 150ms ease;
        }

        button:hover {
            transform: translateY(-1px);
            background: #1e293b;
        }

        button:focus-visible {
            outline: 3px solid #93c5fd;
            outline-offset: 3px;
        }

        @media (prefers-reduced-motion: reduce) {
            button {
                transition: none;
            }
        }
    </style>
</head>
<body>
    <main aria-labelledby="page-title">
        <div class="icon" aria-hidden="true">
            <svg width="34" height="34" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M12 9V13M12 17H12.01M10.3 3.86L1.82 18.5A2 2 0 0 0 3.55 21.5H20.45A2 2 0 0 0 22.18 18.5L13.7 3.86A2 2 0 0 0 10.3 3.86Z" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </div>
        <p class="eyebrow">Service interruption</p>
        <h1 id="page-title">We’ll be back shortly</h1>
        <p class="message">The service is temporarily unavailable while we restore the connection.</p>
        <p class="note">Your information is safe. Please wait a moment and try again.</p>
        <button type="button" onclick="window.location.reload()">Try again</button>
    </main>
</body>
</html>
