{{-- Emblema del colegio en el color del texto actual (máscara sobre el PNG blanco). --}}
<span aria-hidden="true" {{ $attributes->class('inline-block bg-current') }}
      style="-webkit-mask: url('{{ asset('images/logo_cata_emblema.png') }}') center / contain no-repeat; mask: url('{{ asset('images/logo_cata_emblema.png') }}') center / contain no-repeat;"></span>
