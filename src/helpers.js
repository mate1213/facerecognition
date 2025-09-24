import Handlebars from 'handlebars/runtime'

Handlebars.registerHelper('noPhotos', function(count) {
    return n('facerecognition', '%n image', '%n images', count)
})

export const getPersonNameUrl = () => {
    try {
        return new URLSearchParams(window.location.search).get('name')
    } catch {
        const query = window.location.search.substring(1).split('&')
        for (const part of query) {
            const [key, val] = part.split('=')
            if (key === 'name') return decodeURIComponent(val)
        }
    }
    return undefined
}

export const setPersonNameUrl = (personName) => {
    const cleanUrl = window.location.href.split('?')[0]
    let newUrl = cleanUrl
    let title = t('facerecognition', 'Face Recognition')
    if (personName) {
        newUrl = `${cleanUrl}?name=${encodeURIComponent(personName)}`
        title += ` - ${personName}`
    }
    window.history.replaceState({}, title, newUrl)
    document.title = title
}
