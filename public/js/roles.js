const allrpgRolesList = (command, projectId, objType, objId, allrpgRolesListDiv) => new Promise((resolve, reject) => {
    if (allrpgRolesListDiv === undefined) {
        allrpgRolesListDiv = document.getElementById('allrpgRolesListDiv');
    } else {
        allrpgRolesListDiv = document.getElementById(allrpgRolesListDiv);
    }

    if (allrpgRolesListDiv) {
        allrpgRolesListDiv.className += (allrpgRolesListDiv.className ? ' ' : '') + 'allrpgRolesListLoading';

        if (projectId === undefined) {
            if (allrpgRolesListDiv.getAttribute('project_id')) {
                projectId = allrpgRolesListDiv.getAttribute('project_id');
            } else {
                allrpgRolesListDiv.innerHTML = 'Error: project id not set.';
                reject();
            }
        }

        if (objType === undefined) {
            objType = 'group';

            if (allrpgRolesListDiv.getAttribute('data-obj-type')) {
                objType = allrpgRolesListDiv.getAttribute('data-obj-type');
            }
        }

        if (objId === undefined) {
            objId = ['all'];

            if (allrpgRolesListDiv.getAttribute('data-obj-id')) {
                objId = allrpgRolesListDiv.getAttribute('data-obj-id');
            }
        }

        const formData = new FormData();
        formData.append('action', 'get_roles_list');
        formData.append('command', command);
        formData.append('project_id', projectId);
        formData.append('obj_type', objType);
        formData.append('obj_id', objId);

        if (window.dynrequestPath === undefined) {
            window.dynrequestPath = 'https://www.allrpg.info/roles/';
        }

        const xhr = new XMLHttpRequest();
        xhr.open('POST', window.dynrequestPath);
        xhr.send(formData);

        xhr.onreadystatechange = function () {
            if (xhr.readyState == 4) {
                if (xhr.status == 200) {
                    const data = JSON.parse(xhr.responseText);

                    if (data['response'] === 'success') {
                        if (data['response_data']) {
                            allrpgRolesListDiv.innerHTML = data['response_data'];
                            const e = document.getElementById(window.location.hash.substr(1));

                            if (!!e && e.scrollIntoView) {
                                e.scrollIntoView();
                            }
                        } else {
                            allrpgRolesListDiv.innerHTML = 'Error: no data found.';

                            reject();
                        }
                    } else if (data['response'] == 'error') {
                        allrpgRolesListDiv.innerHTML = 'Error: ' + data['response_text'];

                        reject();
                    }

                    allrpgRolesListDiv.className = allrpgRolesListDiv.className.replace(/(?:^|\s)allrpgRolesListLoading(?!\S)/g, '');

                    resolve();
                }
            }
        }
    }
})