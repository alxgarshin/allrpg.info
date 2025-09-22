/** Рулежка: вопросы и модели */

let selectedNodeType = '';
let selectedNodeId = 0;

/** Чарты nlgraph */
if (el('div.nlgraph_chart')) {
    nlgraphApply();
}

if (withDocumentEvents) {

}

/** Визуальный генератор карты связей */
function nlgraphApply() {
    const scriptName = 'nlgraphApply';

    dataElementLoad(
        scriptName,
        document,
        () => {
            getScript('/vendor/nlgraph/nlgraph.min.js').then(() => {
                dataLoaded['libraries'][scriptName] = true;
            });
        },
        function () {
            const nlgraphOptions = {
                "width": 900,
                "height": 650,
                "zoomLens": false,
                "autofit": true,
                "forceSpeed": 200,
                "forceStatic": true,
                "forceCharge": -800,
                "collisionRadius": 60,
                "lineTensionDistance": 10,
                "edgeCurve": "linear"
            };

            const nlgraphChartDiv = el(`#${_('div.nlgraph_chart').attr('id')}`);

            const nlgraphChart = new NLGraph(nlgraphChartDiv, window.nlgraphNodes, window.nlgraphLinks, nlgraphOptions).render();

            //заменяем setNodeLabel
            function computeTspanDy(baseDy, lineHeight, tspanNo) {
                const dyArray = [];
                let lowerBound;

                if (tspanNo % 2 != 0) {
                    lowerBound = baseDy - Math.floor(tspanNo / 2) * lineHeight;
                } else {
                    lowerBound = -(baseDy + (tspanNo / 2 - 1) * lineHeight);
                }

                for (let i = 0; i < tspanNo; ++i) {
                    dyArray.push(lowerBound + i * lineHeight);
                }

                return dyArray;
            }

            nlgraphChart.setNodeLabel = function (nodeId, nodeType, listLabel) {
                const listAttrVals = [];
                const lineHeight = NLGraph.LABEL_LINE_HEIGHT; // em

                for (let i = 0; i < listLabel.length; ++i) {
                    listAttrVals.push(listLabel[i]);
                }

                const tspanDyArray = computeTspanDy(0.2, lineHeight, listLabel.length);

                this.vertices.select(".label").each(function (d) {
                    if (d.id == nodeId && d.type == nodeType) {
                        d3.select(this).text(null);
                        const y = d3.select(this).attr("y");

                        for (let i = 0; i < listAttrVals.length; ++i) {
                            d3.select(this).append("tspan")
                                .attr("x", 0)
                                .attr("y", y)
                                .attr("dy", tspanDyArray[i] + "em")
                                .text(listAttrVals[i]);
                        }

                    }
                    let bbox;

                    try {
                        bbox = this.getBBox();
                    } catch (e) {
                        //mimic bbox bcz FireFox has a bug
                        bbox = {
                            x: this.clientLeft,
                            y: this.clientTop,
                            width: this.clientWidth,
                            height: this.clientHeight
                        }
                    }

                    d.bbox = bbox;

                    //set selected node
                    if (d.id == selectedNodeId && d.type == selectedNodeType) {
                        _(this).closest('g.node').addClass('selected_node').addClass('selected');
                    }
                });

                this.vertices.select(".outline")
                    .attr("x", function (d) {
                        return d.bbox.x
                    })
                    .attr("y", function (d) {
                        return d.bbox.y
                    })
                    .attr("width", function (d) {
                        return d.bbox.width
                    })
                    .attr("height", function (d) {
                        return d.bbox.height
                    });

                return this;

            };

            _each(nlgraphChart.nodes, function (value) {
                nlgraphChart.setNodeLabel(value.id, value.type, value.attr.label);
            });

            _each(nlgraphChart.links, function (value) {
                nlgraphChart.setEdgeLabel(value.source.id, value.source.type, value.target.id, value.target.type, value.etype, ["label"]);
            });

            d3.select(window).on('click', function () {
                nlgraphChart.unHighlight();
                nlgraphChart.unSelect();
                _('.highlighted').removeClass('highlighted');
                _('button.ruling_chart_btn.main').attr('href', '').disable();
            });

            nlgraphChart.vertices.on('click', function (d) {
                _('.highlighted').removeClass('highlighted');
                _(nlgraphChartDiv).addClass('highlighted');

                const nodes = nlgraphChart.getAssociatedNodes(d.id, d.type).map(function (d) {
                    return [d.id, d.type]
                });
                const edges = nlgraphChart.getAssociatedLinks(d.id, d.type).map(function (d) {
                    return [d.source.id, d.source.type, d.target.id, d.target.type, d.etype]
                });

                const nodesThroughEdges = [];
                const edgesThroughEdges = [];

                _each(nodes, function (value) {
                    const self = _(value);

                    if (self[1] == 'ifs_aggregator') {
                        const nodes2 = nlgraphChart.getAssociatedNodes(self[0], self[1]).map(function (d) {
                            return [d.id, d.type]
                        });
                        nodesThroughEdges = nodesThroughEdges.concat(nodes2);

                        const edges2 = nlgraphChart.getAssociatedLinks(self[0], self[1]).map(function (d) {
                            return [d.source.id, d.source.type, d.target.id, d.target.type, d.etype]
                        });
                        edgesThroughEdges = edgesThroughEdges.concat(edges2);
                    }
                });

                nodes = nodes.concat(nodesThroughEdges);
                edges = edges.concat(edgesThroughEdges);

                nlgraphChart.highLight([[d.id, d.type]].concat(nodes), edges);

                if (typeof d.href != 'undefined') {
                    _('button.ruling_chart_btn.main').attr('href', d.href).enable();
                    _('button.ruling_chart_btn.main.switch_to').attr('href', `/ruling_edit/ruling_${d.type}_id=${d.id}`);
                } else {
                    _('button.ruling_chart_btn.main').attr('href', '').disable();
                }
            });
        }
    );
}