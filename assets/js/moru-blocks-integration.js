const settings = window.wc.wcSettings.getSetting('moru_data', {});
const label = window.wp.htmlEntities.decodeEntities(settings.title) || window.wp.i18n.__('Moru Payment', 'moru-payment-gateway');
const Content = () => {
    return window.wp.htmlEntities.decodeEntities(settings.description || '');
};

const Block_Gateway = {
    name: 'moru',
    label: label,
    content: Object.assign(Content, {}),
    edit: Object.assign(Content, {}),
    canMakePayment: () => true,
    ariaLabel: label,
    supports: {
        features: settings.supports,
    },
};

window.wc.wcBlocksRegistry.registerPaymentMethod(Block_Gateway);
