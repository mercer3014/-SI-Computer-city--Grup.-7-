<script setup lang="ts">
import GraficoBarras from '@/components/reportes/GraficoBarras.vue';
import GraficoLineas from '@/components/reportes/GraficoLineas.vue';
import GraficoTorta from '@/components/reportes/GraficoTorta.vue';
import type { DatosGrafico } from '@/lib/formatoReporte';

defineProps<{
    grafico: DatosGrafico;
    moneda?: boolean;
}>();
</script>

<template>
    <div v-if="grafico.tipo !== 'ninguno' && grafico.series.length > 0">
        <GraficoLineas
            v-if="grafico.tipo === 'linea'"
            :etiquetas="grafico.etiquetas"
            :series="grafico.series"
            :moneda="moneda"
        />
        <GraficoTorta
            v-else-if="grafico.tipo === 'torta'"
            :etiquetas="grafico.etiquetas"
            :valores="grafico.series[0]?.valores ?? []"
            :moneda="moneda"
        />
        <GraficoBarras
            v-else
            :etiquetas="grafico.etiquetas"
            :series="grafico.series"
            :moneda="moneda"
        />
    </div>
</template>
