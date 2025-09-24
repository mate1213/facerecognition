// src/personal.js
import Persons from './persons.js';
import $ from 'jquery';
import View from './view.js';
import { getPersonNameUrl } from './helpers.js';

// Initialize Persons and View
const persons = new Persons(OC.generateUrl('/apps/facerecognition'));
const view = new View(persons);

// Get personName from URL
const personName = getPersonNameUrl();

// Always render initial content
view.renderContent();

// Load person if valid, otherwise load all persons
if (personName && personName.trim().length > 0) {
    persons.loadPerson(personName)
        .done(() => view.renderContent())
        .fail(() => {
            OC.Notification.showTemporary(
                t('facerecognition', 'There was an error when trying to find photos of your friend')
            );
        });
} else {
    persons.load()
        .done(() => view.renderContent())
        .fail(() => {
            OC.Notification.showTemporary(
                t('facerecognition', 'There was an error trying to show your friends')
            );
        });
}

// Easter Egg: Konami Code
const egg = new Egg("up,up,down,down,left,right,left,right,b,a", function() {
    if (!OC.isUserAdmin()) {
        OC.Notification.showTemporary(
            t('facerecognition', 'You must be administrator to configure this feature')
        );
        return;
    }

    $.ajax({
        type: 'POST',
        url: OC.generateUrl('apps/facerecognition/setappvalue'),
        data: {
            'type': 'obfuscate_faces',
            'value': 'toggle'
        },
        success: function() {
            location.reload();
        }
    });
}).listen();
