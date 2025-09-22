/** Сообщества */

if (withDocumentEvents) {
    _arSuccess('load_projects_communities_list', function (jsonData, params, target) {
        target.parent().append(jsonData['response_text']);

        target.remove();
    })
}