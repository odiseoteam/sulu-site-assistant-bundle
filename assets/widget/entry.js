// Runs the widget on its own Stimulus application, so the site needs neither Stimulus nor a build.
import { Application } from '@hotwired/stimulus';
import SiteAssistantController from './controllers/site_assistant_controller.js';

Application.start().register('site-assistant', SiteAssistantController);
