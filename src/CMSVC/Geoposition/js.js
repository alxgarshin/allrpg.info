/** Геопозиция игроков */

let getPlayersGeopositionTimeoutTimer = 10000;

let startCoordsLongitude = 36.765450;
let startCoordsLatitude = 55.188735;
let geopositionMap = false;
let iconFeaturesActive = [];
let iconFeaturesActiveLayer = '';

if (el('div.kind_geoposition')) {
    geopositionMap = false;

    openlayersApply();

    /** Центрирование карты */
    _('a.geoposition_map_center').on('click', function () {
        if (geopositionMap) {
            geopositionMap.getView().setCenter(ol.proj.fromLonLat([startCoordsLongitude, startCoordsLatitude]));
        }
    });

    /** Фильтрация пользователей по имени или заявке на карте */
    const savedFeaturesStyles = [];

    _('input#geoposition_search').on('change keyup', function () {
        if (geopositionMap) {
            const searchText = _(this).val().toLowerCase();

            if (searchText == '') {
                getPlayersGeopositionTimeoutTimer = 10000;
                getPlayersGeopositionTimeout();
            } else {
                window.clearTimeout(window['get_players_geoposition']);

                _each(iconFeaturesActiveLayer.getSource().getFeatures(), function (feature, index) {
                    if (feature.get('name').toLowerCase().match(searchText) || feature.get('application_name').toLowerCase().match(searchText)) {
                        if (feature.get('hidden')) {
                            feature.setStyle(savedFeaturesStyles[index]);
                            feature.unset('hidden');
                        }
                    } else {
                        if (!feature.get('hidden')) {
                            savedFeaturesStyles[index] = feature.getStyle();
                            feature.setStyle(new ol.style.Style({}));
                            feature.set('hidden', true);
                        }
                    }
                });
            }
        }
    });
}

if (withDocumentEvents) {
    actionRequestSupressErrorForActions.push('get_players_geoposition');

    _arSuccess('get_players_geoposition', function (jsonData, params, target) {
        iconFeaturesActive = [];

        _each(jsonData['response_data'], function (el) {
            startCoordsLongitude = el.coords_longitude;
            startCoordsLatitude = el.coords_latitude;

            const iconFeature = new ol.Feature({
                geometry: new ol.geom.Point(ol.proj.transform([el.coords_longitude, el.coords_latitude], 'EPSG:4326', 'EPSG:3857')),
                name: el.player,
                application_name: el.name,
                photo: '', /*(el.photo != `` ? `<div class="photoName"><div class="photoName_photo_wrapper"><div class="photoName_photo" style="background-image: url('/scripts/geo_avatar/path=${el.photo}&active=${(el.active ? `1` : `0`)}');"></div></div></div>` : '')*/
                description:
                    `<div><span>${LOCALE.geoposition.sends_coordinates}:</span>${(el.active ? LOCALE.geoposition.yes : LOCALE.geoposition.no)}</div>` +
                    `<div><span>${LOCALE.geoposition.last_send}:</span>${el.last_active}</div>` +
                    `<div><span>${LOCALE.geoposition.application}:</span><a href="/application/${el.application_id}/" target="_blank">${el.name}</a></div>`
            });

            const iconStyle = new ol.style.Style({
                image: new ol.style.Icon(({
                    anchor: [0.5, 0.5],
                    scale: 0.4,
                    src: `/scripts/geo_avatar/path=${el.photo}&active=${(el.active ? '1' : '0')}`,
                    size: [100, 100]
                }))
            });

            iconFeature.setStyle(iconStyle);
            iconFeaturesActive.push(iconFeature);
        })

        if (geopositionMap) {
            //меняем содержание слоя
            iconFeaturesActiveLayer.setSource(new ol.source.Vector({
                features: iconFeaturesActive
            }));
        } else {
            geopositionMapInitialize(iconFeaturesActive);
        }
    })
}

function getPlayersGeopositionTimeout() {
    if (document.addEventListener === undefined || visibilityChangeHidden === undefined || !document[visibilityChangeHidden] || isInStandalone) {
        actionRequest({
            action: 'geoposition/get_players_geoposition'
        });
    }

    window.clearTimeout(window['get_players_geoposition']);
    window['get_players_geoposition'] = window.setTimeout(getPlayersGeopositionTimeout, getPlayersGeopositionTimeoutTimer);
}

/** Карта геопозиции игроков, ее popup и обработчики */
function openlayersApply() {
    const scriptName = 'openlayersApply';

    dataElementLoad(
        scriptName,
        document,
        () => {
            cssLoad('openlayers', '/vendor/openlayers/ol.min.css');

            getScript('/vendor/openlayers/ol.min.js').then(() => {
                dataLoaded['libraries'][scriptName] = true;
            });
        },
        function () {
            getPlayersGeopositionTimeout();
        }
    );
}

function geopositionMapInitialize(iconFeaturesActive) {
    if (geopositionMap) {
    } else {
        iconFeaturesActiveLayer = new ol.layer.Vector({
            source: new ol.source.Vector({
                features: iconFeaturesActive
            })
        });

        geopositionMap = new ol.Map({
            target: 'geopositionMap',
            layers: [
                new ol.layer.Tile({
                    source: new ol.source.BingMaps({
                        key: 'An8lzsPgr6jUv0-V3vkBp7HdoWurrxF04J2tVwrxIP7EtEfhIQBx0PfxTOaUAsYa',
                        imagerySet: "AerialWithLabels"
                    })
                }),
                iconFeaturesActiveLayer
            ],
            view: new ol.View({
                center: ol.proj.fromLonLat([startCoordsLongitude, startCoordsLatitude]),
                zoom: 17,
                maxZoom: 19
            })
        });

        const overlay = el('#geoposition_map_overlay');

        const popup = new ol.Overlay({
            element: overlay,
            positioning: 'bottom-center',
            stopEvent: false,
            offset: [-10, 10]
        });
        geopositionMap.addOverlay(popup);

        // display popup on click
        geopositionMap.on('click', function (evt) {
            const feature = geopositionMap.forEachFeatureAtPixel(evt.pixel,
                function (feature) {
                    return feature;
                });

            if (feature) {
                const coordinates = feature.getGeometry().getCoordinates();

                popup.setPosition(coordinates);

                _(overlay)
                    .hide()
                    .html(`${feature.get(`photo`)}<div class="geoposition_map_overlay_name">${feature.get('name')}</div><div class="geoposition_map_overlay_content">${feature.get(`description`)}</div>`)
                    .show();
            } else {
                _(overlay).hide();
            }
        });

        // change mouse cursor when over marker
        geopositionMap.on('pointermove', function (e) {
            if (e.dragging) {
                _(overlay).hide();
                return;
            }

            const pixel = geopositionMap.getEventPixel(e.originalEvent);
            const hit = geopositionMap.hasFeatureAtPixel(pixel);

            _('.geoposition_map').css('cursor', (hit ? 'pointer' : ''));
        });

        geopositionMap.on('movestart', function () {
            _(overlay).hide();
        });

        _('.geoposition_map_center').show();
    }
}