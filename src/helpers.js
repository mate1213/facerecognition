export function getPersonNameUrl() {
    const parser = document.createElement('a');
    parser.href = window.location.href;
    const query = parser.search.substring(1);
    const vars = query.split('&');
    for (const v of vars) {
        const pair = v.split('=');
        if (pair[0] === 'name') return decodeURIComponent(pair[1]);
    }
    return undefined;
}

export function setPersonNameUrl(personName) {
    let cleanUrl = window.location.href.split("?")[0];
    let title = t('facerecognition', 'Face Recognition');
    if (personName) {
        cleanUrl += '?name=' + encodeURIComponent(personName);
        title += ' - ' + personName;
    }
    window.history.replaceState({}, title, cleanUrl);
    document.title = title;
}