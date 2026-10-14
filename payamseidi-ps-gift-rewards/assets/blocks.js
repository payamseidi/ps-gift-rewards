/* global wp, wc */
(function () {
    'use strict';
    if (!window.wp || !window.wc || !wc.blocksCheckout || !wc.blocksCheckout.ExperimentalOrderMeta || !wc.blocksCheckout.extensionCartUpdate) {
        return;
    }
    const h = wp.element.createElement;
    const useState = wp.element.useState;
    const { __ } = wp.i18n;
    const { registerPlugin } = wp.plugins;
    const { ExperimentalOrderMeta, extensionCartUpdate } = wc.blocksCheckout;
    const ns = 'ps-gift-rewards';
    const t = (source) => __(source, 'payamseidi-ps-gift-rewards');

    function Selector(props) {
        const data = (props.extensions || {})[ns] || {};
        const [busy, setBusy] = useState(false);
        const [error, setError] = useState('');
        if (!data.visible) { return null; }
        const gifts = Array.isArray(data.gifts) ? data.gifts : [];
        function updateGift(id) {
            if (busy) { return; }
            setBusy(true);
            setError('');
            extensionCartUpdate({
                namespace: ns,
                data: { action: id ? 'choose' : 'remove', id: id || 0 }
            }).catch(function (err) {
                const message = err && err.message ? err.message : t('We could not update your gift. Please try again.');
                setError(message);
            }).finally(function () {
                setBusy(false);
            });
        }
        return h('section', { className: 'psgr-panel psgr-blocks-panel', 'aria-label': t('Gift rewards') },
            h('h3', null, data.title || t('Your complimentary gift')),
            !data.eligible
                ? h('p', null, data.message || t('Add more products to unlock a gift.'), ' ', data.remaining || '')
                : h('div', null,
                    h('p', null, data.message || t('Choose one complimentary gift with your order.')),
                    h('div', { className: 'psgr-grid' }, gifts.map(function (gift) {
                        return h('article', { className: 'psgr-card', key: String(gift.id) },
                            gift.image ? h('img', { src: gift.image, alt: '', loading: 'lazy' }) : null,
                            h('strong', null, gift.name),
                            h('span', null, gift.price, ' · ', h('b', null, t('FREE'))),
                            h('button', {
                                type: 'button', className: 'button psgr-choice',
                                disabled: busy || gift.id === data.selected_id,
                                'aria-pressed': gift.id === data.selected_id,
                                onClick: function () { updateGift(gift.id); }
                            }, gift.id === data.selected_id ? t('Selected') : t('Choose gift'))
                        );
                    })),
                    gifts.length === 0 ? h('p', null, t('No gifts are currently available.')) : null,
                    data.selected_id ? h('button', {
                        type: 'button', className: 'psgr-remove', disabled: busy,
                        onClick: function () { updateGift(0); }
                    }, t('Remove gift')) : null
                ),
            error ? h('p', { role: 'alert', className: 'psgr-status' }, error) : null
        );
    }
    registerPlugin('ps-gift-rewards', {
        render: function () { return h(ExperimentalOrderMeta, null, h(Selector)); },
        scope: 'woocommerce-checkout'
    });
}());
