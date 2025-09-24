// src/personal.js
import Persons from './persons.js'
import View from './view.js'
import { getPersonNameUrl } from './helpers.js'

const persons = new Persons(OC.generateUrl('/apps/facerecognition'))
const view = new View(persons)

const personName = getPersonNameUrl()
if (personName !== undefined) {
    view.renderContent()
    persons.loadPerson(personName).done(function () {
        view.renderContent()
    }).fail(function () {
        OC.Notification.showTemporary(t('facerecognition', 'There was an error when trying to find photos of your friend'))
    })
} else {
    view.renderContent()
    persons.load().done(function () {
        view.renderContent()
    }).fail(function () {
        OC.Notification.showTemporary(t('facerecognition', 'There was an error trying to show your friends'))
    })
}

// Easter Egg Konami Code
const egg = new Egg("up,up,down,down,left,right,left,right,b,a", function() {
    if (!OC.isUserAdmin()) {
        OC.Notification.showTemporary(t('facerecognition', 'You must be administrator to configure this feature'))
        return
    }
    $.ajax({
        type: 'POST',
        url: OC.generateUrl('apps/facerecognition/setappvalue'),
        data: {
            'type': 'obfuscate_faces',
            'value': 'toggle'
        },
        success: function () {
            location.reload()
        }
    })
}).listen()
