{{-- Spain requires the EU common logo and the authorisation details on every page of a website
     that sells medicines; the logo links to the official register of authorised pharmacy websites. --}}
<footer class="site-footer">
    <div class="site-footer__inner wrap">
        <div class="authorisation">
            <a class="eu-logo" href="{{ config('pharmacy.eu_logo_url') }}">[EU common logo, links to the AEMPS register]</a>
            <p>We are authorised by {{ config('pharmacy.authority') }} to sell medicines that don’t need a prescription online. The EU logo links to the official register of pharmacy websites, so you can check we’re on it. Pharmacist in charge: {{ config('pharmacy.pharmacist') }}, member no. {{ config('pharmacy.pharmacist_number') }}.</p>
        </div>
        <div class="site-footer__bottom">
            <p>{{ config('pharmacy.legal_name') }}. Licence no. {{ config('pharmacy.licence') }}.</p>
            <nav class="legal-nav" aria-label="Legal">
                <a href="#">Terms of sale</a>
                <a href="#">Privacy</a>
                <a href="#">Cookies</a>
                <a href="#">Accessibility</a>
            </nav>
        </div>
    </div>
</footer>
