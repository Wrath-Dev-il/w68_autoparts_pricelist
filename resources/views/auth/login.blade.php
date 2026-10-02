<!DOCTYPE html>
<html lang="en" data-auth-mode="{{ session('auth_mode', old('auth_mode', 'login')) }}">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title>W68 Special Store</title>
<link rel="stylesheet" href="{{ asset('css/w68-login.css') }}?v=20261002-canva-v10">
<script>
(function () {
    var ua = navigator.userAgent || '';
    var touchTablet = navigator.maxTouchPoints > 1 && Math.min(screen.width || 9999, screen.height || 9999) <= 1100;
    var isHandheld =
        /iPhone|iPad|iPod|Android|Mobile|Tablet/i.test(ua) ||
        (/Macintosh/i.test(ua) && navigator.maxTouchPoints > 1) ||
        touchTablet;

    if (isHandheld) {
        document.documentElement.classList.add('w68-handheld');
    }
})();
</script>
<style id="w68-auth-critical-20261002-v3">
:root{--w68-green:#13300f;--w68-maroon:#890001;--w68-yellow:#ffea32}
html,body{margin:0!important;min-height:100%!important;background:#fff!important}
body{font-family:Arial,Helvetica,sans-serif!important}
.auth-stage{min-height:100vh!important;min-height:100dvh!important;background:#fff!important}

/* DESKTOP / LAPTOP */
html:not(.w68-handheld) .auth-stage{
    display:flex!important;
    align-items:center!important;
    justify-content:center!important;
    padding:0 28px!important;
}
html:not(.w68-handheld) .auth-shell{
    width:min(1180px,100%)!important;
    min-height:100vh!important;
    display:grid!important;
    grid-template-columns:48% 52%!important;
    gap:18px!important;
    align-items:center!important;
}
html:not(.w68-handheld) .auth-form-column{
    width:100%!important;
    max-width:520px!important;
    justify-self:center!important;
}
html:not(.w68-handheld) .auth-brand{margin:0 0 10px!important}
html:not(.w68-handheld) .auth-logo{
    width:110px!important;height:110px!important;object-fit:contain!important
}
html:not(.w68-handheld) .auth-brand-line{
    display:flex!important;align-items:center!important;justify-content:center!important;
    gap:4px!important;color:var(--w68-green)!important;font-size:18px!important;font-weight:900!important
}
html:not(.w68-handheld) .brand-cart-icon{width:20px!important;height:20px!important;color:var(--w68-green)!important}
html:not(.w68-handheld) .auth-title{
    margin:0 0 10px 8px!important;color:var(--w68-maroon)!important;
    font-size:31px!important;line-height:1!important;font-weight:500!important
}
html:not(.w68-handheld) .auth-form-box{
    width:100%!important;padding:18px 14px 16px!important;
    border:5px solid var(--w68-green)!important;border-radius:18px!important;
    background:#fff!important;box-shadow:none!important
}
html:not(.w68-handheld) .auth-field{
    display:grid!important;grid-template-columns:88px 1fr!important;
    align-items:center!important;gap:0!important;margin:0 0 10px!important
}
html:not(.w68-handheld) .register-form-box .auth-field{grid-template-columns:92px 1fr!important}
html:not(.w68-handheld) .auth-field label{
    margin:0!important;color:#111!important;font-size:17px!important;font-weight:700!important;line-height:1.05!important
}
html:not(.w68-handheld) .auth-field>input,
html:not(.w68-handheld) .password-wrap>input{
    width:100%!important;height:32px!important;padding:0 10px!important;
    border:2px solid #111!important;border-radius:13px!important;
    -webkit-border-radius:13px!important;-webkit-appearance:none!important;appearance:none!important;
    background:#fff!important;background-color:#fff!important;color:#111!important;
    box-shadow:none!important;outline:none!important
}
html:not(.w68-handheld) .password-wrap{position:relative!important;width:100%!important}
html:not(.w68-handheld) .password-wrap>input{padding-right:62px!important}
html:not(.w68-handheld) .password-toggle{
    position:absolute!important;right:5px!important;top:50%!important;transform:translateY(-50%)!important;
    height:22px!important;min-width:48px!important;border:0!important;background:transparent!important;
    color:var(--w68-maroon)!important;font-size:10px!important;font-weight:800!important
}
html:not(.w68-handheld) .forgot-link{
    display:block!important;margin:-5px 0 3px auto!important;padding:0!important;
    border:0!important;background:transparent!important;color:var(--w68-maroon)!important;
    font-size:10px!important;font-weight:700!important;text-decoration:underline!important
}
html:not(.w68-handheld) .remember-row{
    display:flex!important;align-items:center!important;gap:5px!important;
    margin:0 0 7px 92px!important;color:#111!important;font-size:12px!important
}
html:not(.w68-handheld) .remember-row input{width:15px!important;height:15px!important}
html:not(.w68-handheld) .auth-actions{
    display:grid!important;gap:4px!important;margin:0 0 0 88px!important
}
html:not(.w68-handheld) .register-form-box .auth-actions{margin-left:92px!important}
html:not(.w68-handheld) .auth-button{
    width:100%!important;height:24px!important;min-height:24px!important;padding:0 8px!important;
    border:2px solid #111!important;border-radius:4px!important;font-size:12px!important;font-weight:700!important
}
html:not(.w68-handheld) .auth-button-primary{background:var(--w68-green)!important;color:var(--w68-yellow)!important}
html:not(.w68-handheld) .auth-button-secondary{background:var(--w68-maroon)!important;color:var(--w68-yellow)!important}
html:not(.w68-handheld) .auth-art-panel{
    min-height:100vh!important;height:100vh!important;display:flex!important;
    align-items:center!important;justify-content:flex-end!important;overflow:hidden!important
}
html:not(.w68-handheld) .auth-art{
    width:min(100%,540px)!important;max-height:94vh!important;height:auto!important;
    object-fit:contain!important;object-position:right center!important
}
html:not(.w68-handheld) .register-grid{display:block!important}
html:not(.w68-handheld) .mobile-register-brand{display:none!important}

/* IPAD / TABLET / PHONE */
html.w68-handheld,
html.w68-handheld body{
    width:100%!important;min-height:100%!important;background:#fff!important
}
html.w68-handheld .auth-stage{
    width:100%!important;min-height:100vh!important;min-height:100dvh!important;
    display:flex!important;align-items:flex-start!important;justify-content:center!important;
    padding:0!important;background:#fff!important;overflow:auto!important
}
html.w68-handheld .auth-shell{
    position:relative!important;width:100%!important;max-width:none!important;
    min-height:100vh!important;min-height:100dvh!important;margin:0!important;padding:0!important;
    display:block!important;overflow:hidden!important;border:4px solid var(--w68-green)!important;
    border-radius:15px!important;background:#fff!important;box-shadow:none!important
}
html.w68-handheld .auth-art-panel{
    position:absolute!important;inset:0!important;z-index:0!important;width:100%!important;height:100%!important;
    min-height:0!important;margin:0!important;display:block!important;overflow:hidden!important
}
html.w68-handheld .auth-art{
    position:absolute!important;inset:0!important;width:100%!important;height:100%!important;
    max-height:none!important;object-fit:cover!important;object-position:center top!important;
    opacity:.72!important;filter:none!important
}
html.w68-handheld .auth-form-column{
    position:relative!important;z-index:2!important;width:100%!important;max-width:none!important;
    min-height:100vh!important;min-height:100dvh!important;margin:0!important;
    padding:18px 18px 16px!important;display:block!important
}
html.w68-handheld .auth-panel{
    width:100%!important;margin:0!important;padding:0!important;border:0!important;
    background:transparent!important;box-shadow:none!important
}
html.w68-handheld .auth-form-box{
    width:100%!important;margin:0!important;padding:0!important;border:0!important;
    border-radius:0!important;background:transparent!important;box-shadow:none!important
}
html.w68-handheld .auth-brand{
    display:flex!important;flex-direction:column!important;align-items:center!important;
    margin:0 0 28px!important
}
html.w68-handheld .auth-logo{
    width:92px!important;height:92px!important;object-fit:contain!important
}
html.w68-handheld .auth-brand-line{
    display:flex!important;align-items:center!important;justify-content:center!important;
    gap:4px!important;color:var(--w68-green)!important;font-size:19px!important;font-weight:900!important
}
html.w68-handheld .brand-cart-icon{width:20px!important;height:20px!important;color:var(--w68-green)!important}
html.w68-handheld .auth-title{
    margin:0 0 26px!important;color:var(--w68-maroon)!important;
    font-size:31px!important;line-height:1!important;font-weight:800!important
}
html.w68-handheld .auth-field,
html.w68-handheld .register-form-box .auth-field{
    display:grid!important;grid-template-columns:100px 1fr!important;
    align-items:center!important;gap:0!important;margin:0 0 14px!important
}
html.w68-handheld .auth-field label{
    margin:0!important;color:#111!important;font-size:16px!important;
    line-height:1.05!important;font-weight:800!important;text-transform:uppercase!important
}
html.w68-handheld .auth-field>input,
html.w68-handheld .password-wrap>input{
    width:100%!important;height:31px!important;padding:0 9px!important;
    border:2px solid #111!important;border-radius:13px!important;
    -webkit-border-radius:13px!important;-webkit-appearance:none!important;appearance:none!important;
    background:#fff!important;background-color:#fff!important;color:#111!important;
    box-shadow:none!important;outline:none!important
}
html.w68-handheld .password-wrap{position:relative!important;width:100%!important}
html.w68-handheld .password-wrap>input{padding-right:58px!important}
html.w68-handheld .password-toggle{
    position:absolute!important;right:5px!important;top:50%!important;transform:translateY(-50%)!important;
    height:22px!important;min-width:46px!important;border:0!important;background:transparent!important;
    color:var(--w68-maroon)!important;font-size:9px!important;font-weight:800!important
}
html.w68-handheld .forgot-link{
    display:block!important;margin:-8px 0 28px auto!important;padding:0!important;
    border:0!important;background:transparent!important;color:var(--w68-maroon)!important;
    font-size:10px!important;font-weight:700!important;text-decoration:underline!important
}
html.w68-handheld .remember-row{display:none!important}
html.w68-handheld .auth-actions,
html.w68-handheld .register-form-box .auth-actions{
    display:grid!important;gap:8px!important;margin:0!important
}
html.w68-handheld .auth-button{
    width:100%!important;height:40px!important;min-height:40px!important;
    border:2px solid #111!important;border-radius:4px!important;
    font-size:18px!important;font-weight:500!important
}
html.w68-handheld .auth-button-primary{background:var(--w68-green)!important;color:var(--w68-yellow)!important}
html.w68-handheld .auth-button-secondary{background:var(--w68-maroon)!important;color:var(--w68-yellow)!important}
html.w68-handheld .mobile-register-brand{display:none!important}

/* login screen placement */
html.w68-handheld[data-auth-mode="login"] .auth-brand{margin-top:0!important}
html.w68-handheld[data-auth-mode="login"] [data-auth-panel="login"]{margin-top:0!important}

/* register screen placement */
html.w68-handheld[data-auth-mode="register"] .auth-brand{display:none!important}
html.w68-handheld[data-auth-mode="register"] .auth-title{margin:0 0 0!important}
html.w68-handheld[data-auth-mode="register"] .mobile-register-brand{
    display:flex!important;align-items:center!important;justify-content:center!important;gap:4px!important;
    margin:34px 0 24px!important;color:var(--w68-green)!important;font-size:19px!important;font-weight:900!important
}
html.w68-handheld[data-auth-mode="register"] .register-form-box .auth-field{margin-bottom:16px!important}

/* force tablet layout even when Safari requests desktop site */
@media (max-width:1100px) and (pointer:coarse){
    html:not(.w68-handheld){background:#fff!important}
}
@media (max-width:430px){
    html.w68-handheld .auth-form-column{padding:14px 12px 14px!important}
    html.w68-handheld .auth-logo{width:80px!important;height:80px!important}
    html.w68-handheld .auth-brand-line,
    html.w68-handheld[data-auth-mode="register"] .mobile-register-brand{font-size:17px!important}
    html.w68-handheld .auth-title{font-size:27px!important}
    html.w68-handheld .auth-field,
    html.w68-handheld .register-form-box .auth-field{grid-template-columns:98px 1fr!important}
    html.w68-handheld .auth-field label{font-size:15px!important}
    html.w68-handheld .auth-button{font-size:16px!important}
}

/* W68_AUTH_FINAL_SCALE_AND_IPAD_20261002_V4 */

/* Desktop: render at approximately the user's preferred 125% browser-zoom size. */
html:not(.w68-handheld) .auth-stage{
    overflow:hidden!important;
}

html:not(.w68-handheld) .auth-shell{
    width:min(1240px,94vw)!important;
    min-height:650px!important;
    height:auto!important;
    grid-template-columns:49% 51%!important;
    gap:20px!important;
}

html:not(.w68-handheld) .auth-form-column{
    max-width:590px!important;
}

html:not(.w68-handheld) .auth-logo{
    width:132px!important;
    height:132px!important;
}

html:not(.w68-handheld) .auth-brand-line{
    font-size:22px!important;
}

html:not(.w68-handheld) .brand-cart-icon{
    width:23px!important;
    height:23px!important;
}

html:not(.w68-handheld) .auth-title{
    margin:0 0 13px 8px!important;
    font-size:38px!important;
}

html:not(.w68-handheld) .auth-form-box{
    padding:22px 18px 20px!important;
    border-width:5px!important;
}

html:not(.w68-handheld) .auth-field{
    grid-template-columns:108px 1fr!important;
    margin-bottom:13px!important;
}

html:not(.w68-handheld) .register-form-box .auth-field{
    grid-template-columns:112px 1fr!important;
}

html:not(.w68-handheld) .auth-field label{
    font-size:20px!important;
}

html:not(.w68-handheld) .auth-field>input,
html:not(.w68-handheld) .password-wrap>input{
    height:40px!important;
    border-radius:15px!important;
    -webkit-border-radius:15px!important;
    font-size:17px!important;
}

html:not(.w68-handheld) .password-toggle{
    height:26px!important;
    min-width:54px!important;
    font-size:11px!important;
}

html:not(.w68-handheld) .forgot-link{
    margin:-6px 0 5px auto!important;
    font-size:11px!important;
}

html:not(.w68-handheld) .remember-row{
    margin:0 0 9px 112px!important;
    font-size:14px!important;
}

html:not(.w68-handheld) .remember-row input{
    width:18px!important;
    height:18px!important;
}

html:not(.w68-handheld) .auth-actions{
    margin-left:108px!important;
    gap:6px!important;
}

html:not(.w68-handheld) .register-form-box .auth-actions{
    margin-left:112px!important;
}

html:not(.w68-handheld) .auth-button{
    height:34px!important;
    min-height:34px!important;
    font-size:14px!important;
    line-height:30px!important;
}

html:not(.w68-handheld) .auth-art-panel{
    min-height:650px!important;
    height:auto!important;
}

html:not(.w68-handheld) .auth-art{
    width:min(100%,610px)!important;
    max-height:690px!important;
}

/* Remove every legacy overlay that was hiding the iPad artwork. */
html.w68-handheld .auth-art-panel::before,
html.w68-handheld .auth-art-panel::after{
    content:none!important;
    display:none!important;
    background:none!important;
}

/* iPad / tablet: match the Canva card instead of filling the whole iPad page. */
html.w68-handheld .auth-stage{
    min-height:100vh!important;
    min-height:100dvh!important;
    display:flex!important;
    align-items:center!important;
    justify-content:center!important;
    padding:14px!important;
    overflow:auto!important;
    background:#fff!important;
}

html.w68-handheld .auth-shell{
    position:relative!important;
    width:min(620px,calc(100vw - 28px))!important;
    height:min(707px,calc(100dvh - 28px))!important;
    min-height:560px!important;
    max-height:707px!important;
    margin:auto!important;
    padding:0!important;
    display:block!important;
    overflow:hidden!important;
    border:4px solid #13300f!important;
    border-radius:15px!important;
    background:#fff!important;
}

html.w68-handheld .auth-art-panel{
    position:absolute!important;
    inset:0!important;
    z-index:0!important;
    width:100%!important;
    height:100%!important;
    min-height:0!important;
    margin:0!important;
    display:block!important;
    overflow:hidden!important;
    pointer-events:none!important;
}

html.w68-handheld .auth-art{
    position:absolute!important;
    inset:0!important;
    display:block!important;
    width:100%!important;
    height:100%!important;
    max-height:none!important;
    object-fit:cover!important;
    object-position:center center!important;
    opacity:.72!important;
    visibility:visible!important;
    filter:none!important;
}

html.w68-handheld .auth-form-column{
    position:relative!important;
    z-index:2!important;
    width:100%!important;
    height:100%!important;
    min-height:0!important;
    padding:17px 18px 16px!important;
    display:block!important;
    background:transparent!important;
}

html.w68-handheld .auth-brand{
    margin:0 0 20px!important;
}

html.w68-handheld .auth-logo{
    width:100px!important;
    height:100px!important;
}

html.w68-handheld .auth-brand-line{
    font-size:19px!important;
}

html.w68-handheld .auth-title{
    margin:0 0 24px!important;
    font-size:31px!important;
}

html.w68-handheld .auth-field,
html.w68-handheld .register-form-box .auth-field{
    grid-template-columns:100px 1fr!important;
    margin-bottom:14px!important;
}

html.w68-handheld .auth-field label{
    font-size:16px!important;
}

html.w68-handheld .auth-field>input,
html.w68-handheld .password-wrap>input{
    height:34px!important;
    border-radius:14px!important;
    -webkit-border-radius:14px!important;
    background:rgba(255,255,255,.96)!important;
}

html.w68-handheld .forgot-link{
    margin:-7px 0 24px auto!important;
    font-size:10px!important;
}

html.w68-handheld .auth-actions,
html.w68-handheld .register-form-box .auth-actions{
    gap:8px!important;
}

html.w68-handheld .auth-button{
    height:42px!important;
    min-height:42px!important;
    font-size:18px!important;
}

/* Login Canva placement */
html.w68-handheld[data-auth-mode="login"] .auth-brand{
    margin-top:0!important;
    margin-bottom:22px!important;
}

html.w68-handheld[data-auth-mode="login"] .auth-title{
    margin-bottom:26px!important;
}

/* Register Canva placement */
html.w68-handheld[data-auth-mode="register"] .auth-brand{
    display:none!important;
}

html.w68-handheld[data-auth-mode="register"] .auth-title{
    margin:0!important;
}

html.w68-handheld[data-auth-mode="register"] .mobile-register-brand{
    display:flex!important;
    align-items:center!important;
    justify-content:center!important;
    gap:4px!important;
    margin:34px 0 23px!important;
    color:#13300f!important;
    font-size:19px!important;
    font-weight:900!important;
}

html.w68-handheld[data-auth-mode="register"] .register-form-box .auth-field{
    margin-bottom:16px!important;
}

/* Small phones: keep the same composition but allow the card to use available height. */
@media(max-width:430px){
    html.w68-handheld .auth-stage{
        padding:7px!important;
    }

    html.w68-handheld .auth-shell{
        width:calc(100vw - 14px)!important;
        height:calc(100dvh - 14px)!important;
        min-height:540px!important;
    }

    html.w68-handheld .auth-form-column{
        padding:12px!important;
    }

    html.w68-handheld .auth-logo{
        width:82px!important;
        height:82px!important;
    }

    html.w68-handheld .auth-brand{
        margin-bottom:16px!important;
    }

    html.w68-handheld .auth-brand-line,
    html.w68-handheld[data-auth-mode="register"] .mobile-register-brand{
        font-size:17px!important;
    }

    html.w68-handheld .auth-title{
        font-size:27px!important;
        margin-bottom:18px!important;
    }

    html.w68-handheld .auth-field,
    html.w68-handheld .register-form-box .auth-field{
        grid-template-columns:96px 1fr!important;
        margin-bottom:11px!important;
    }

    html.w68-handheld .auth-field label{
        font-size:14px!important;
    }

    html.w68-handheld .auth-field>input,
    html.w68-handheld .password-wrap>input{
        height:31px!important;
    }

    html.w68-handheld .forgot-link{
        margin-bottom:16px!important;
    }

    html.w68-handheld .auth-button{
        height:38px!important;
        min-height:38px!important;
        font-size:16px!important;
    }
}

/* W68_UPLOADED_FLATICON_CART_20261002_V8 */
.uploaded-cart-icon{
    display:inline-block!important;
    background-color:#13300f!important;
    -webkit-mask-image:url("{{ asset('images/shopping-cart-removebg-preview.png') }}")!important;
    mask-image:url("{{ asset('images/shopping-cart-removebg-preview.png') }}")!important;
    -webkit-mask-repeat:no-repeat!important;
    mask-repeat:no-repeat!important;
    -webkit-mask-position:center!important;
    mask-position:center!important;
    -webkit-mask-size:contain!important;
    mask-size:contain!important;
}
html:not(.w68-handheld) .auth-brand-line .uploaded-cart-icon{
    width:25px!important;
    height:25px!important;
    margin-left:4px!important;
    flex:0 0 25px!important;
    transform:translateY(1px)!important;
}
html:not(.w68-handheld)[data-auth-mode="login"] .auth-title{
    font-weight:900!important;
}
html.w68-handheld .auth-brand-line .uploaded-cart-icon,
html.w68-handheld .mobile-register-brand .uploaded-cart-icon{
    width:20px!important;
    height:20px!important;
    margin-left:3px!important;
    flex:0 0 20px!important;
}


/* W68_HANDHELD_REFERENCE_20261002_V9 */
html.w68-handheld body,
html.w68-handheld .auth-stage{
    background:#fff!important;
}

html.w68-handheld .auth-stage{
    width:100%!important;
    min-height:100vh!important;
    min-height:100dvh!important;
    padding:8px!important;
    display:flex!important;
    align-items:center!important;
    justify-content:center!important;
    overflow:auto!important;
}

html.w68-handheld .auth-shell{
    position:relative!important;
    width:min(620px,calc(100vw - 16px))!important;
    height:auto!important;
    min-height:0!important;
    max-height:none!important;
    aspect-ratio:360 / 421!important;
    margin:auto!important;
    padding:0!important;
    display:block!important;
    overflow:hidden!important;
    border:4px solid #13300f!important;
    border-radius:15px!important;
    background:#fff!important;
    box-shadow:none!important;
}

html.w68-handheld .auth-art-panel,
html.w68-handheld .auth-art-panel::before,
html.w68-handheld .auth-art-panel::after{
    position:absolute!important;
    inset:0!important;
    width:100%!important;
    height:100%!important;
    margin:0!important;
    padding:0!important;
    min-height:0!important;
    display:block!important;
    content:none!important;
    background:none!important;
    overflow:hidden!important;
    pointer-events:none!important;
}

html.w68-handheld .auth-art-panel{
    z-index:0!important;
}

html.w68-handheld .auth-art{
    position:absolute!important;
    inset:0!important;
    width:100%!important;
    height:100%!important;
    max-width:none!important;
    max-height:none!important;
    display:block!important;
    object-fit:cover!important;
    object-position:center center!important;
    opacity:.82!important;
    visibility:visible!important;
    filter:none!important;
}

html.w68-handheld .auth-form-column{
    position:absolute!important;
    inset:0!important;
    z-index:2!important;
    width:100%!important;
    height:100%!important;
    min-height:0!important;
    max-width:none!important;
    margin:0!important;
    padding:10px 18px 14px!important;
    display:block!important;
    background:transparent!important;
}

html.w68-handheld .auth-brand{
    display:flex!important;
    flex-direction:column!important;
    align-items:center!important;
    justify-content:flex-start!important;
    margin:0 0 10px!important;
}

html.w68-handheld .auth-logo{
    width:78px!important;
    height:78px!important;
    object-fit:contain!important;
    margin:0!important;
}

html.w68-handheld .auth-brand-line{
    display:flex!important;
    align-items:center!important;
    justify-content:center!important;
    gap:3px!important;
    margin-top:3px!important;
    color:#13300f!important;
    font-size:18px!important;
    line-height:1!important;
    font-weight:900!important;
    white-space:nowrap!important;
}

html.w68-handheld .auth-brand-line .uploaded-cart-icon{
    width:20px!important;
    height:20px!important;
    margin-left:1px!important;
    flex:0 0 20px!important;
    background-color:#13300f!important;
}

html.w68-handheld .auth-panel{
    position:static!important;
    width:100%!important;
    margin:0!important;
    padding:0!important;
    border:0!important;
    border-radius:0!important;
    background:transparent!important;
    box-shadow:none!important;
}

html.w68-handheld .auth-title{
    margin:0 0 9px 5px!important;
    color:#890001!important;
    font-size:20px!important;
    line-height:1!important;
    font-weight:900!important;
    text-align:left!important;
}

html.w68-handheld .auth-form-box{
    position:static!important;
    width:100%!important;
    margin:0!important;
    padding:0!important;
    border:0!important;
    border-radius:0!important;
    background:transparent!important;
    box-shadow:none!important;
}

html.w68-handheld .auth-field,
html.w68-handheld .register-form-box .auth-field{
    display:grid!important;
    grid-template-columns:78px 1fr!important;
    align-items:center!important;
    gap:0!important;
    margin:0 0 8px!important;
}

html.w68-handheld .auth-field label{
    margin:0!important;
    color:#111!important;
    font-size:14px!important;
    line-height:1!important;
    font-weight:900!important;
    text-transform:uppercase!important;
}

html.w68-handheld .auth-field>input,
html.w68-handheld .password-wrap>input{
    width:100%!important;
    height:22px!important;
    min-height:22px!important;
    padding:0 7px!important;
    border:2px solid #111!important;
    border-radius:12px!important;
    -webkit-border-radius:12px!important;
    -webkit-appearance:none!important;
    appearance:none!important;
    background:rgba(255,255,255,.96)!important;
    color:#111!important;
    box-shadow:none!important;
    outline:none!important;
    font-size:13px!important;
}

html.w68-handheld .password-wrap{
    position:relative!important;
    width:100%!important;
}

html.w68-handheld .password-wrap>input{
    padding-right:42px!important;
}

html.w68-handheld .password-toggle{
    position:absolute!important;
    top:50%!important;
    right:4px!important;
    transform:translateY(-50%)!important;
    height:18px!important;
    min-width:34px!important;
    padding:0!important;
    border:0!important;
    background:transparent!important;
    color:#890001!important;
    font-size:8px!important;
    line-height:18px!important;
    font-weight:900!important;
}

html.w68-handheld .forgot-link{
    display:block!important;
    margin:-3px 0 0 auto!important;
    padding:0!important;
    border:0!important;
    background:transparent!important;
    color:#890001!important;
    font-size:9px!important;
    line-height:1!important;
    font-weight:700!important;
    text-decoration:underline!important;
}

html.w68-handheld .remember-row{
    display:none!important;
}

html.w68-handheld .auth-actions,
html.w68-handheld .register-form-box .auth-actions{
    position:absolute!important;
    left:18px!important;
    right:18px!important;
    bottom:28px!important;
    margin:0!important;
    display:grid!important;
    gap:7px!important;
}

html.w68-handheld .auth-button{
    width:100%!important;
    height:35px!important;
    min-height:35px!important;
    padding:0 8px!important;
    border:2px solid #111!important;
    border-radius:4px!important;
    font-size:15px!important;
    line-height:31px!important;
    font-weight:500!important;
}

html.w68-handheld .auth-button-primary{
    background:#13300f!important;
    color:#ffea32!important;
}

html.w68-handheld .auth-button-secondary{
    background:#890001!important;
    color:#ffea32!important;
}

/* Login reference positioning */
html.w68-handheld[data-auth-mode="login"] .auth-brand{
    display:flex!important;
    margin-bottom:10px!important;
}

html.w68-handheld[data-auth-mode="login"] .auth-title{
    margin-top:0!important;
}

/* Register reference positioning */
html.w68-handheld[data-auth-mode="register"] .auth-brand{
    display:none!important;
}

html.w68-handheld[data-auth-mode="register"] .auth-title{
    margin:6px 0 36px 5px!important;
    font-size:22px!important;
}

html.w68-handheld[data-auth-mode="register"] .mobile-register-brand{
    position:absolute!important;
    top:72px!important;
    left:0!important;
    right:0!important;
    display:flex!important;
    align-items:center!important;
    justify-content:center!important;
    gap:3px!important;
    margin:0!important;
    color:#13300f!important;
    font-size:18px!important;
    line-height:1!important;
    font-weight:900!important;
    white-space:nowrap!important;
}

html.w68-handheld[data-auth-mode="register"] .mobile-register-brand .uploaded-cart-icon{
    width:20px!important;
    height:20px!important;
    margin-left:1px!important;
    flex:0 0 20px!important;
    background-color:#13300f!important;
}

html.w68-handheld[data-auth-mode="register"] .register-form-box{
    padding-top:35px!important;
}

html.w68-handheld[data-auth-mode="register"] .register-form-box .auth-field{
    grid-template-columns:92px 1fr!important;
    margin-bottom:10px!important;
}

html.w68-handheld[data-auth-mode="register"] .register-form-box .auth-field label{
    font-size:14px!important;
    line-height:1.05!important;
}

html.w68-handheld[data-auth-mode="register"] .register-form-box .auth-actions{
    bottom:27px!important;
}

/* Scale the same composition for tablets/iPad. */
@media (min-width:431px){
    html.w68-handheld .auth-form-column{
        padding:16px 28px 20px!important;
    }

    html.w68-handheld .auth-logo{
        width:108px!important;
        height:108px!important;
    }

    html.w68-handheld .auth-brand-line{
        font-size:24px!important;
    }

    html.w68-handheld .auth-brand-line .uploaded-cart-icon{
        width:26px!important;
        height:26px!important;
        flex-basis:26px!important;
    }

    html.w68-handheld .auth-title{
        margin-left:8px!important;
        margin-bottom:15px!important;
        font-size:28px!important;
    }

    html.w68-handheld .auth-field,
    html.w68-handheld .register-form-box .auth-field{
        grid-template-columns:118px 1fr!important;
        margin-bottom:12px!important;
    }

    html.w68-handheld .auth-field label{
        font-size:19px!important;
    }

    html.w68-handheld .auth-field>input,
    html.w68-handheld .password-wrap>input{
        height:32px!important;
        min-height:32px!important;
        border-radius:16px!important;
        font-size:16px!important;
    }

    html.w68-handheld .forgot-link{
        font-size:11px!important;
    }

    html.w68-handheld .auth-actions,
    html.w68-handheld .register-form-box .auth-actions{
        left:28px!important;
        right:28px!important;
        bottom:40px!important;
        gap:10px!important;
    }

    html.w68-handheld .auth-button{
        height:50px!important;
        min-height:50px!important;
        font-size:20px!important;
        line-height:46px!important;
    }

    html.w68-handheld[data-auth-mode="register"] .auth-title{
        margin:12px 0 52px 8px!important;
        font-size:29px!important;
    }

    html.w68-handheld[data-auth-mode="register"] .mobile-register-brand{
        top:104px!important;
        font-size:24px!important;
    }

    html.w68-handheld[data-auth-mode="register"] .mobile-register-brand .uploaded-cart-icon{
        width:26px!important;
        height:26px!important;
        flex-basis:26px!important;
    }

    html.w68-handheld[data-auth-mode="register"] .register-form-box{
        padding-top:48px!important;
    }

    html.w68-handheld[data-auth-mode="register"] .register-form-box .auth-field{
        grid-template-columns:128px 1fr!important;
        margin-bottom:14px!important;
    }

    html.w68-handheld[data-auth-mode="register"] .register-form-box .auth-field label{
        font-size:18px!important;
    }
}


/* W68_HANDHELD_SINGLE_BACKGROUND_20261002_V10 */
html.w68-handheld .auth-shell{
    background-color:#fff!important;
    background-image:url("{{ asset('images/Shopping Cart of Automotive Parts.png') }}")!important;
    background-repeat:no-repeat!important;
    background-position:center center!important;
    background-size:cover!important;
}

html.w68-handheld .auth-art-panel,
html.w68-handheld .auth-art-panel::before,
html.w68-handheld .auth-art-panel::after,
html.w68-handheld .auth-art{
    display:none!important;
    content:none!important;
    visibility:hidden!important;
    width:0!important;
    height:0!important;
    min-width:0!important;
    min-height:0!important;
    margin:0!important;
    padding:0!important;
    border:0!important;
}

/* Keep the form completely transparent over the one-piece background. */
html.w68-handheld .auth-form-column,
html.w68-handheld .auth-panel,
html.w68-handheld .auth-form-box{
    background:transparent!important;
    background-image:none!important;
}

</style>
<script src="{{ asset('js/w68-auth.js') }}?v=20261002-canva-v10" defer></script>
</head>
<body>
@php
    $showOtpModal = (bool) ($otpRequired ?? false)
        || session('otp_required')
        || $errors->has('otp')
        || in_array((string) session('w68_otp_purpose', ''), ['login', 'register', 'forgot'], true);

    $visibleOtpPurpose = (string) ($otpPurpose ?? session('otp_purpose', session('w68_otp_purpose', 'login')));
    $visibleOtpEmail = (string) ($otpEmail ?? session('otp_email', ''));
    $otpExpiresAt = (int) session('w68_otp_expires_at', 0);
    $serverNow = now()->timestamp;
@endphp

<main class="auth-stage">
    <div class="auth-shell">
        <section class="auth-form-column">
            <div class="auth-brand">
                <img class="auth-logo" src="{{ asset('images/sidebar_logo.png') }}" alt="W68">
                <div class="auth-brand-line">
                    <span>W68 SPECIAL STORE</span>
                    <span class="uploaded-cart-icon" aria-hidden="true"></span>
                </div>
            </div>

            @if (session('status'))
                <div class="auth-message auth-message-success">{{ session('status') }}</div>
            @endif

            @if ($errors->any() && !$errors->has('otp'))
                <div class="auth-message auth-message-error">{{ $errors->first() }}</div>
            @endif

            <section class="auth-panel" data-auth-panel="login">
                <h1 class="auth-title">LOGIN</h1>

                <form method="POST" action="{{ route('login.attempt') }}" class="auth-form auth-form-box">
                    @csrf
                    <input type="hidden" name="auth_mode" value="login">

                    <div class="auth-field">
                        <label for="email">email:</label>
                        <input id="email" type="email" name="email" value="{{ old('email') }}" autocomplete="email" required>
                    </div>

                    <div class="auth-field">
                        <label for="password">Password:</label>
                        <div class="password-wrap">
                            <input id="password" type="password" name="password" autocomplete="current-password" required>
                            <button type="button" class="password-toggle" data-password-toggle aria-controls="password">SHOW</button>
                        </div>
                    </div>

                    <button type="button" class="forgot-link" data-forgot-password>forgot password?</button>

                    <label class="remember-row">
                        <input type="checkbox" name="remember" value="1" @checked(old('remember'))>
                        <span>remember me?</span>
                    </label>

                    <div class="auth-actions">
                        <button type="submit" class="auth-button auth-button-primary">LOGIN</button>
                        <button type="button" class="auth-button auth-button-secondary" data-auth-switch="register">REGISTER</button>
                    </div>
                </form>

                <form method="POST" action="{{ route('password.forgot') }}" data-forgot-password-form hidden>
                    @csrf
                    <input type="hidden" name="email" data-forgot-password-email>
                </form>
            </section>

            <section class="auth-panel" data-auth-panel="register" hidden>
                <h1 class="auth-title">REGISTER</h1>

                <div class="mobile-register-brand" aria-hidden="true">
                    <span>W68 SPECIAL STORE</span>
                    <span class="uploaded-cart-icon" aria-hidden="true"></span>
                </div>

                <form method="POST" action="{{ route('register.attempt') }}" class="auth-form auth-form-box register-form-box">
                    @csrf
                    <input type="hidden" name="auth_mode" value="register">

                    <div class="register-grid">
                        <div class="auth-field">
                            <label for="username">User Name</label>
                            <input id="username" type="text" name="username" value="{{ old('username') }}" autocomplete="username" required>
                        </div>

                        <div class="auth-field">
                            <label for="register_email">email:</label>
                            <input id="register_email" type="email" name="register_email" value="{{ old('register_email') }}" autocomplete="email" required>
                        </div>

                        <div class="auth-field">
                            <label for="register_password">Password:</label>
                            <div class="password-wrap">
                                <input id="register_password" type="password" name="register_password" autocomplete="new-password" required>
                                <button type="button" class="password-toggle" data-password-toggle aria-controls="register_password">SHOW</button>
                            </div>
                        </div>

                        <div class="auth-field">
                            <label for="register_password_confirmation">Re Type Password</label>
                            <div class="password-wrap">
                                <input id="register_password_confirmation" type="password" name="register_password_confirmation" autocomplete="new-password" required>
                                <button type="button" class="password-toggle" data-password-toggle aria-controls="register_password_confirmation">SHOW</button>
                            </div>
                        </div>
                    </div>

                    <div class="auth-actions">
                        <button type="submit" class="auth-button auth-button-primary">REGISTER</button>
                        <button type="button" class="auth-button auth-button-secondary" data-auth-switch="login">GO BACK TO LOGIN</button>
                    </div>
                </form>
            </section>
        </section>

        <aside class="auth-art-panel" aria-hidden="true">
            <img src="{{ asset('images/Shopping Cart of Automotive Parts.png') }}" alt="" class="auth-art">
        </aside>
    </div>
</main>

<div
    class="otp-modal{{ $showOtpModal ? ' is-open' : '' }}"
    data-otp-modal
    data-otp-open="{{ $showOtpModal ? 'true' : 'false' }}"
    data-otp-expires-at="{{ $otpExpiresAt }}"
    data-server-now="{{ $serverNow }}"
    @if (!$showOtpModal) hidden @endif
    aria-hidden="{{ $showOtpModal ? 'false' : 'true' }}"
>
    <div class="otp-backdrop" data-otp-cancel></div>

    <section class="otp-card" role="dialog" aria-modal="true" aria-labelledby="otp-title">
        <div class="otp-heading-band">
            <h2 id="otp-title">OTP CODE</h2>
            <p>One Time Password will be sent to your registered email. Please carefully check the email from W68 Auto Parts.</p>
        </div>

        @if ($visibleOtpEmail !== '')
            <div class="otp-email">{{ $visibleOtpEmail }}</div>
        @endif

        @if ($errors->has('otp'))
            <div class="auth-message auth-message-error otp-error">{{ $errors->first('otp') }}</div>
        @endif

        <form method="POST" action="{{ route('otp.verify') }}" class="otp-form">
            @csrf
            <div class="otp-countdown">expired within: <strong data-otp-countdown>--:--</strong></div>
            <input id="otp" type="text" name="otp" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required data-otp-input>

            <div class="otp-actions" data-otp-active-actions>
                <button type="button" class="otp-button otp-button-cancel" data-otp-cancel>CANCEL</button>
                <button type="submit" class="otp-button otp-button-primary">VERIFY</button>
            </div>
        </form>

        <div class="otp-actions" data-otp-expired-actions hidden>
            <button type="button" class="otp-button otp-button-cancel" data-otp-cancel>CANCEL</button>
            <form method="POST" action="{{ route('otp.resend') }}" class="otp-resend-form">
                @csrf
                <button type="submit" class="otp-button otp-button-primary">RESEND</button>
            </form>
        </div>
    </section>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var forgotButton = document.querySelector('[data-forgot-password]');
    var forgotForm = document.querySelector('[data-forgot-password-form]');
    var forgotEmail = document.querySelector('[data-forgot-password-email]');
    var loginEmail = document.getElementById('email');

    if (!forgotButton || !forgotForm || !forgotEmail || !loginEmail) {
        return;
    }

    forgotButton.addEventListener('click', function () {
        if (!loginEmail.checkValidity()) {
            loginEmail.reportValidity();
            loginEmail.focus();
            return;
        }

        forgotEmail.value = loginEmail.value.trim();
        forgotForm.submit();
    });
});
</script>
</body>
</html>