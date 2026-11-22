<script setup lang="ts">
import { esNumerica, formatearValor } from '@/lib/formatoReporte';
import type { ResultadoReporte } from '@/types/reportes';

defineProps<{
    resultado: Pick<ResultadoReporte, 'columnas' | 'filas' | 'totales'>;
}>();
</script>

<template>
    <div class="cc-log__table-wrap">
        <table class="cc-log__table" data-test="tabla-reporte">
            <thead>
                <tr>
                    <th
                        v-for="columna in resultado.columnas"
                        :key="columna.clave"
                        :class="{ 'cc-tabla__num': esNumerica(columna.tipo) }"
                    >
                        {{ columna.etiqueta }}
                    </th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="(fila, indice) in resultado.filas" :key="indice">
                    <td
                        v-for="columna in resultado.columnas"
                        :key="columna.clave"
                        :class="{ 'cc-tabla__num': esNumerica(columna.tipo) }"
                    >
                        {{ formatearValor(fila[columna.clave], columna.tipo) }}
                    </td>
                </tr>
            </tbody>
            <tfoot v-if="Object.keys(resultado.totales).length > 0">
                <tr class="cc-tabla__total">
                    <td
                        v-for="columna in resultado.columnas"
                        :key="columna.clave"
                        :class="{ 'cc-tabla__num': esNumerica(columna.tipo) }"
                    >
                        {{
                            formatearValor(
                                resultado.totales[columna.clave],
                                columna.tipo,
                            )
                        }}
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>
</template>

<style scoped>
.cc-tabla__num {
    text-align: right;
    font-variant-numeric: tabular-nums;
}

.cc-tabla__total td {
    border-top: 2px solid var(--cc-accent);
    font-weight: 700;
}
</style>
