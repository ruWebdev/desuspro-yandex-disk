<template>
    <slot name="PageTitle"></slot>



    <div class="page-header d-print-none m-0 bottom-shadow">
        <div class="container-xl">

            <div class="row p-0 align-items-center page-subnavigation">
                <div class="pt-2 pb-2 ps-0 pe-0 d-flex justify-content-between">
                    <!--
                    <div class="col">                       
                        <div class="page-pretitle">
                            <slot name="BreadCrumbs"></slot>
                        </div>
                        <h2 class="page-title">
                            <slot name="PageName"></slot>
                        </h2>
                    </div>
                    -->
                    <!-- Page title actions -->
                    <div class="col-12 ms-auto d-print-none">
                        <div class="btn-list">
                            <slot name="TopButtons" />
                        </div>
                    </div>
                </div>

            </div>

        </div>
    </div>


    <div class="page-body mt-0 page-body-no-overflow" id="main-content-area" style="height: 1px; margin-bottom: 0px;">
        <div class="container-xl h-100 main-content-container-overflow" id="main-content-container">
            <slot />

        </div>
    </div>
</template>

<script>

export default {



}

</script>
