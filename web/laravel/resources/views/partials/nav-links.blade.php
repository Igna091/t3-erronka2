<a href="{{ route('shop.index') }}" @if (request()->routeIs('shop.*', 'basket.*', 'checkout.*', 'orders.*', 'payment.*')) aria-current="page" @endif>Shop</a>
<a href="{{ route('home') }}#refills">Prescriptions</a>
<a href="{{ route('home') }}#services">Services</a>
<a href="{{ route('home') }}#dose-packs">Dose packs</a>
<a href="{{ route('home') }}#visit">Visit us</a>
