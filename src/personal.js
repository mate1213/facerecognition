import { Persons } from './persons.js';
import { View } from './view.js';
import { getPersonNameUrl } from './helpers.js';

const persons = new Persons(OC.generateUrl('/apps/facerecognition'), jQuery);
const view = new View(persons, jQuery);

const personName = getPersonNameUrl();
view.renderContent();

if (personName !== undefined) {
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

// Konami code toggle
var egg = new Egg("up,up,down,down,left,right,left,right,b,a", function() {
    if (!OC.isUserAdmin()) {
        OC.Notification.showTemporary(
            t('facerecognition', 'You must be administrator to configure this feature')
        );
        return;
    }

    const deferred = jQuery.Deferred();

    jQuery.ajax({
        type: 'POST',
        url: OC.generateUrl('apps/facerecognition/setappvalue'),
        data: {
            'type': 'obfuscate_faces',
            'value': 'toggle'
        },
        headers: {
            'OC-RequestToken': OC.requestToken,
            'X-Requested-With': 'XMLHttpRequest'
        }
    }).done(() => {
        location.reload();
        deferred.resolve();
    }).fail(() => {
        OC.Notification.showTemporary(
            t('facerecognition', 'Failed to toggle face obfuscation')
        );
        deferred.reject();
    });

    return deferred.promise();
}).listen();
