var config = {
    paths: {
        'bynderjs': 'DamConsultants_Bynder/js/bynder',
        'select2': 'DamConsultants_Bynder/js/select2'
    },
    shim: {
        'bynderjs': {
            deps: ['jquery']
        },
        'select2': {
            deps: ['jquery']
        },
    },
    map: {
        '*': {
            'Magento_PageBuilder/template/form/element/html-code.html': 'DamConsultants_Bynder/template/form/element/html-code.html',
            'Magento_PageBuilder/js/form/element/html-code': 'DamConsultants_Bynder/js/form/element/html-code',
            'Magento_PageBuilder/template/content-type/video/default/master.html': 'DamConsultants_Bynder/template/content-type/video/default/master.html',
            'Magento_PageBuilder/template/content-type/video/default/preview.html': 'DamConsultants_Bynder/template/content-type/video/default/preview.html',
        },
    }
};