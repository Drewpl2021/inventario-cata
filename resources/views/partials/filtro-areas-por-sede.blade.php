{{--
    Filtra las opciones de un <select> de áreas según la sede elegida.
    Uso: <select name="area_id" data-sede-select="ID_DEL_SELECT_DE_SEDE"> con
    opciones <option data-sede="{{ $area->sede_id }}">. El servidor valida lo mismo
    con la regla AreaPerteneceASede.
--}}
<script nonce="{{ Vite::cspNonce() }}">
    document.querySelectorAll('select[data-sede-select]').forEach(function (selectArea) {
        const selectSede = document.getElementById(selectArea.dataset.sedeSelect);
        if (!selectSede) return;

        const filtrar = function () {
            const sede = selectSede.value;
            selectArea.querySelectorAll('option[data-sede]').forEach(function (opcion) {
                const visible = sede !== '' && opcion.dataset.sede === sede;
                opcion.hidden = !visible;
                opcion.disabled = !visible;
                if (!visible && opcion.selected) {
                    selectArea.value = '';
                }
            });
        };

        selectSede.addEventListener('change', filtrar);
        filtrar();
    });
</script>
