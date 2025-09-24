// src/view.js
import $ from 'jquery';
import Handlebars from 'handlebars';
import { partials } from './partials.js';
import { setPersonNameUrl } from './helpers.js';

export default class View {
    constructor(persons) {
        this._persons = persons;
        this._enabled = OCP.InitialState.loadState('facerecognition', 'user-enabled');
        this._hasUnamed = OCP.InitialState.loadState('facerecognition', 'has-unamed');
        this._hasHidden = OCP.InitialState.loadState('facerecognition', 'has-hidden');
        this._observer = lozad('.lozad');
        this._bulkAction = undefined;
        this._hiddenClusters = undefined;

        // Register partials once
        Object.entries(partials).forEach(([name, tpl]) => {
            Handlebars.registerPartial(name, tpl);
        });
    }

    async renderContent() {
        const context = {
            loaded: this._persons.isLoaded(),
            appName: t('facerecognition', 'Face Recognition'),
            welcomeHint: t('facerecognition', 'Here you can see photos of your friends that are recognized'),
            enableDescription: t('facerecognition', 'Analyze my images and group my loved ones with similar faces'),
            loadingMsg: t('facerecognition', 'Looking for your recognized friends'),
            showMoreButton: t('facerecognition', 'Review face groups'),
            showIgnoredButton: t('facerecognition', 'Review ignored people'),
            emptyMsg: t('facerecognition', 'The analysis is disabled'),
            emptyHint: t('facerecognition', 'Enable it to find your loved ones'),
            renameHint: t('facerecognition', 'Rename'),
            hideHint: t('facerecognition', 'Hide it'),
            loadingIcon: OC.imagePath('core', 'loading.gif'),
            bulkAssignNameHint: t('facerecognition', 'Please assign a name to this person.'),
            bulkSave: t('facerecognition', 'Save')
        };

        if (this._enabled) {
            context.enabled = true;
            context.hasUnamed = this._hasUnamed;
            context.hasHidden = this._hasHidden;
            context.persons = this._persons.getPersons();
            context.bulkAction = this._bulkAction;
            context.hidden = this._hiddenClusters;
            context.reviewPeopleMsg = t('facerecognition', 'Review people found');
            context.reviewIgnoredMsg = t('facerecognition', 'Review ignored people');
            context.emptyMsg = t('facerecognition', 'Your friends have not been recognized yet');
            context.emptyHint = t('facerecognition', 'Please, be patient');
        }

        const person = this._persons.getActivePerson();
        if (person) {
            context.personName = person.name;
            context.personImages = person.images;
        }

        const clustersByName = this._persons.getClustersByName();
        if (clustersByName.length > 0) {
            context.clustersByName = clustersByName;
        }

        // Render main template
        const html = Handlebars.templates['personal'](context);
        $('#div-content').html(html);

        // Observe lazy-loaded images
        this._observer.observe();

        // Update URL
        setPersonNameUrl(person ? person.name : undefined);

        // Setup autocomplete for hidden clusters
        if (this._hiddenClusters) {
            this._hiddenClusters.forEach(cluster => {
                new AutoComplete({
                    input: document.getElementById(cluster.id + "-name-input"),
                    lookup(query) {
                        return new Promise(resolve => {
                            $.get(OC.generateUrl('/apps/facerecognition/autocomplete/' + query))
                                .done(names => resolve(names));
                        });
                    },
                    silent: true,
                    highlight: false
                });
            });
        }

        // Bind UI actions
        this.bindActions();
    }

    bindActions() {
        const self = this;

        // Enable/disable face recognition
        $('#enableFacerecognition').off('click').on('click', function () {
            const enabled = $(this).is(':checked');
            if (!enabled) {
                OC.dialogs.confirm(
                    t('facerecognition', 'You will lose all the information analyzed, and if you re-enable it, you will start from scratch.'),
                    t('facerecognition', 'Do you want to deactivate the grouping by faces?'),
                    result => {
                        if (result) self.setEnabledUser(false);
                        else $('#enableFacerecognition').prop('checked', true);
                    },
                    true
                );
            } else {
                self.setEnabledUser(true);
            }
        });

        // TODO: Add remaining actions like rename/hide clusters and persons
        // ... keep your previous event bindings here ...
    }

    setEnabledUser(enabled) {
        const self = this;
        $.ajax({
            type: 'POST',
            url: OC.generateUrl('apps/facerecognition/setuservalue'),
            data: { type: 'enabled', value: enabled },
            success() {
                OC.Notification.showTemporary(enabled
                    ? t('facerecognition', 'The analysis is enabled, please be patient, you will soon see your friends here.')
                    : t('facerecognition', 'The analysis is disabled. Soon all the information found for facial recognition will be removed.')
                );
                self._enabled = enabled;
                self._persons.load().done(() => self.renderContent());
            }
        });
    }
}
