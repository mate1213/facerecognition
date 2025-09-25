import Handlebars from 'handlebars';
import { partials } from './partials/index.js';
import personalTemplate from './templates/personal.handlebars';
import { setPersonNameUrl } from './helpers.js';

Object.entries(partials).forEach(([name, tpl]) => {
    Handlebars.registerPartial(name, tpl);
});

Handlebars.registerHelper('noPhotos', function(count) {
    return n('facerecognition', '%n image', '%n images', count);
});

export class View {
    constructor(persons) {
        this._persons = persons;
        this._enabled = OCP.InitialState.loadState('facerecognition', 'user-enabled');
        this._hasUnamed = OCP.InitialState.loadState('facerecognition', 'has-unamed');
        this._hasHidden = OCP.InitialState.loadState('facerecognition', 'has-hidden');
        this._observer = lozad('.lozad');
    }

    renderContent() {
        const context = {
            loaded: this._persons.isLoaded(),
            appName: t('facerecognition', 'Face Recognition')
        };

        const person = this._persons.getActivePerson();
        if (person) {
            context.personName = person.name;
            context.personImages = person.images;
        }

        const clustersByName = this._persons.getClustersByName();
        if (clustersByName.length > 0) context.clustersByName = clustersByName;

        const html = personalTemplate(context);
        document.getElementById('div-content').innerHTML = html;

        this._observer.observe();
        if (person) setPersonNameUrl(person.name); else setPersonNameUrl();
    }
}
