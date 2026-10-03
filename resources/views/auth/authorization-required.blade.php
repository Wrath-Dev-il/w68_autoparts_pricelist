<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>W68 Unauthorized Access</title>
    <style>
        :root{
            --w68-green:#13300f;
            --w68-maroon:#890001;
            --w68-yellow:#ffea32;
            --w68-cream:#fff8d8;
        }

        *{
            box-sizing:border-box;
        }

        html,body{
            width:100%;
            min-height:100%;
            margin:0;
        }

        body{
            min-height:100vh;
            display:flex;
            align-items:center;
            justify-content:center;
            background:#f5f5ef;
            font-family:Arial,Helvetica,sans-serif;
            color:#111;
            overflow:auto;
        }

        .authorization-card{
            position:relative;
            width:min(680px,100vw);
            height:min(820px,100vh);
            min-height:620px;
            overflow:hidden;
            border:4px solid var(--w68-green);
            border-radius:18px;
            background:var(--w68-cream);
        }

        .authorization-bg{
            position:absolute;
            top:0;
            right:0;
            bottom:0;
            left:0;
            width:100%;
            height:100%;
            object-fit:cover;
            object-position:center center;
            opacity:.72;
            z-index:0;
        }

        .authorization-content{
            position:relative;
            z-index:2;
            width:100%;
            height:100%;
            padding:16px 18px 14px;
            display:flex;
            flex-direction:column;
            align-items:center;
            text-align:center;
        }

        .authorization-logo{
            width:104px;
            height:104px;
            object-fit:contain;
            flex:0 0 auto;
        }

        .authorization-brand{
            margin-top:3px;
            display:flex;
            align-items:center;
            justify-content:center;
            gap:4px;
            color:var(--w68-green);
            font-size:24px;
            line-height:1;
            font-weight:900;
            white-space:nowrap;
        }

        .authorization-cart{
            width:25px;
            height:25px;
            flex:0 0 25px;
            display:inline-block;
            background-color:var(--w68-green);
            -webkit-mask-image:url("{{ asset('images/shopping-cart-removebg-preview.png') }}");
            mask-image:url("{{ asset('images/shopping-cart-removebg-preview.png') }}");
            -webkit-mask-repeat:no-repeat;
            mask-repeat:no-repeat;
            -webkit-mask-position:center;
            mask-position:center;
            -webkit-mask-size:contain;
            mask-size:contain;
        }

        .authorization-title{
            margin:28px 0 0;
            color:#190000;
            font-size:28px;
            line-height:1.05;
            font-weight:900;
            text-shadow:
                -1px -1px 0 #ffd8d8,
                1px -1px 0 #ffd8d8,
                -1px 1px 0 #ffd8d8,
                1px 1px 0 #ffd8d8;
        }

        .authorization-message{
            margin:32px auto 0;
            max-width:520px;
            color:#240000;
            font-size:24px;
            line-height:1.25;
            font-weight:900;
            text-shadow:
                -1px -1px 0 #ffe2e2,
                1px -1px 0 #ffe2e2,
                -1px 1px 0 #ffe2e2,
                1px 1px 0 #ffe2e2;
        }

        .authorization-reason{
            margin:12px auto 0;
            max-width:540px;
            color:var(--w68-maroon);
            font-size:13px;
            line-height:1.35;
            font-weight:800;
        }

        .authorization-contact{
            margin-top:34px;
            color:#180000;
            font-size:21px;
            line-height:1.18;
            font-weight:900;
            text-shadow:
                -1px -1px 0 #ffe2e2,
                1px -1px 0 #ffe2e2,
                -1px 1px 0 #ffe2e2,
                1px 1px 0 #ffe2e2;
        }

        .authorization-contact .heading{
            margin-bottom:2px;
        }

        .authorization-footer{
            margin-top:auto;
            padding-bottom:4px;
            color:#180000;
            font-size:17px;
            line-height:1.45;
            font-weight:900;
            text-shadow:
                -1px -1px 0 #ffe2e2,
                1px -1px 0 #ffe2e2,
                -1px 1px 0 #ffe2e2,
                1px 1px 0 #ffe2e2;
        }

        /* W68_UNAUTHORIZED_DESKTOP_REFERENCE_20261003_V3 */
        @media (min-width:701px){
            body{
                width:100vw;
                height:100vh;
                min-height:100vh;
                margin:0;
                display:block;
                overflow:hidden;
                background:var(--w68-cream);
            }

            .authorization-card{
                position:relative;
                width:100vw;
                height:100vh;
                min-height:0;
                max-width:none;
                max-height:none;
                margin:0;
                border:0;
                border-radius:0;
                overflow:hidden;
                background:
                    radial-gradient(circle, rgba(112,96,34,.15) 1px, transparent 1.15px) 0 0 / 12px 12px,
                    var(--w68-cream);
            }

            /* Keep the complete customer/cart artwork visible like the reference. */
            .authorization-bg{
                position:absolute;
                top:3vh;
                right:0;
                bottom:auto;
                left:auto;
                width:42vw;
                height:94vh;
                max-width:none;
                object-fit:contain;
                object-position:right center;
                opacity:1;
                z-index:0;
            }

            .authorization-content{
                position:relative;
                z-index:2;
                width:50vw;
                height:100vh;
                margin:0;
                padding:2vh 0 0;
                display:block;
                text-align:center;
            }

            .authorization-logo{
                display:block;
                width:clamp(82px,17vh,132px);
                height:clamp(82px,17vh,132px);
                margin:0 auto;
                object-fit:contain;
            }

            .authorization-brand{
                margin:1.2vh 0 0;
                display:flex;
                align-items:center;
                justify-content:center;
                gap:5px;
                color:var(--w68-green);
                font-size:clamp(19px,2.15vw,29px);
                line-height:1;
                font-weight:900;
                white-space:nowrap;
            }

            .authorization-cart{
                width:clamp(21px,2vw,27px);
                height:clamp(21px,2vw,27px);
                flex-basis:clamp(21px,2vw,27px);
            }

            /* Dark-green rounded message box, sized from the viewport like the target. */
            .authorization-content::after{
                content:"";
                position:absolute;
                left:1.3vw;
                top:32vh;
                width:46vw;
                height:51vh;
                border:4px solid var(--w68-green);
                border-radius:18px;
                pointer-events:none;
                z-index:-1;
            }

            .authorization-title{
                position:absolute;
                top:36.5vh;
                left:1.3vw;
                width:46vw;
                margin:0;
                padding:0;
                color:#190000;
                font-size:clamp(17px,1.55vw,22px);
                line-height:1;
                font-weight:900;
                text-align:center;
                text-shadow:
                    -1px -1px 0 #ffd8d8,
                    1px -1px 0 #ffd8d8,
                    -1px 1px 0 #ffd8d8,
                    1px 1px 0 #ffd8d8;
            }

            .authorization-message{
                position:absolute;
                top:43.5vh;
                left:1.3vw;
                width:46vw;
                max-width:none;
                margin:0;
                padding:0;
                color:#240000;
                font-size:clamp(16px,1.5vw,21px);
                line-height:1.28;
                font-weight:900;
                text-align:center;
                text-shadow:
                    -1px -1px 0 #ffe2e2,
                    1px -1px 0 #ffe2e2,
                    -1px 1px 0 #ffe2e2,
                    1px 1px 0 #ffe2e2;
            }

            .authorization-reason{
                display:none;
            }

            .authorization-contact{
                position:absolute;
                top:60vh;
                left:1.3vw;
                width:46vw;
                margin:0;
                padding:0;
                color:#180000;
                font-size:clamp(15px,1.45vw,20px);
                line-height:1.22;
                font-weight:900;
                text-align:center;
                text-shadow:
                    -1px -1px 0 #ffe2e2,
                    1px -1px 0 #ffe2e2,
                    -1px 1px 0 #ffe2e2,
                    1px 1px 0 #ffe2e2;
            }

            .authorization-contact .heading{
                margin-bottom:3px;
            }

            .authorization-footer{
                position:absolute;
                left:1.1vw;
                bottom:2.2vh;
                width:48vw;
                margin:0;
                padding:0;
                color:#180000;
                font-size:clamp(12px,1.05vw,15px);
                line-height:1.75;
                font-weight:900;
                text-align:left;
                white-space:nowrap;
                text-shadow:
                    -1px -1px 0 #ffe2e2,
                    1px -1px 0 #ffe2e2,
                    -1px 1px 0 #ffe2e2,
                    1px 1px 0 #ffe2e2;
            }
        }

        /* W68_UNAUTHORIZED_HANDHELD_REFERENCE_20261003 */
        @media (max-width:700px){
            body{
                display:block;
                background:var(--w68-cream);
            }

            .authorization-card{
                width:100vw;
                height:100vh;
                min-height:0;
                max-width:none;
                max-height:none;
                margin:0;
                border-width:4px;
                border-radius:16px;
            }

            .authorization-content{
                padding:10px 10px 9px;
            }

            .authorization-logo{
                width:80px;
                height:80px;
            }

            .authorization-brand{
                margin-top:2px;
                font-size:19px;
            }

            .authorization-cart{
                width:21px;
                height:21px;
                flex-basis:21px;
            }

            .authorization-title{
                margin-top:26px;
                font-size:18px;
            }

            .authorization-message{
                margin-top:28px;
                max-width:330px;
                font-size:18px;
                line-height:1.32;
            }

            .authorization-reason{
                margin-top:9px;
                max-width:340px;
                font-size:10px;
                line-height:1.3;
            }

            .authorization-contact{
                margin-top:26px;
                font-size:17px;
                line-height:1.2;
            }

            .authorization-footer{
                padding:0 0 1px;
                font-size:13px;
                line-height:1.45;
            }
        }

        @media (max-width:430px){
            .authorization-content{
                padding:8px 8px 7px;
            }

            .authorization-logo{
                width:70px;
                height:70px;
            }

            .authorization-brand{
                font-size:17px;
            }

            .authorization-cart{
                width:19px;
                height:19px;
                flex-basis:19px;
            }

            .authorization-title{
                margin-top:22px;
                font-size:17px;
            }

            .authorization-message{
                margin-top:24px;
                max-width:310px;
                font-size:17px;
            }

            .authorization-contact{
                margin-top:24px;
                font-size:16px;
            }

            .authorization-footer{
                font-size:12px;
            }
        }
    </style>
</head>
<body>
    <main class="authorization-card">
        <img
            class="authorization-bg"
            src="{{ asset('images/Shopping Cart of Automotive Parts.png') }}"
            alt=""
            aria-hidden="true"
        >

        <section class="authorization-content">
            <img
                class="authorization-logo"
                src="{{ asset('images/sidebar_logo.png') }}"
                alt="W68"
            >

            <div class="authorization-brand">
                <span>W68 SPECIAL STORE</span>
                <span class="authorization-cart" aria-hidden="true"></span>
            </div>

            <h1 class="authorization-title">UNAUTHORIZED ACCESS</h1>

            <div class="authorization-message">
                YOU ARE NOT AUTHORIZED TO<br>
                ACCESS THIS PAGE
            </div>

            @if (!empty($authorizationMessage))
                <div class="authorization-reason">{{ $authorizationMessage }}</div>
            @endif

            <div class="authorization-contact">
                <div class="heading">CONTACT US AT:</div>
                <div>Tel. Nos. 8553-9092 / 8829-0480</div>
                <div>Mobile No. 0917-3239-605</div>
                <div>VIBER: 0946-8818-468</div>
            </div>

            <div class="authorization-footer">
                <div>ALL RIGHTS RESERVED TO: W68 AUTO PARTS &amp; SERVICE CENTER</div>
                <div>CEO: WARREN YU</div>
            </div>
        </section>
    </main>
</body>
</html>
