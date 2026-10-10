// @flow
import {viewRegistry} from 'sulu-admin-bundle/containers';
import Conversation from './views/Conversation';
import Statistics from './views/Statistics';

viewRegistry.add('odiseo_site_assistant.conversation', Conversation);
viewRegistry.add('odiseo_site_assistant.statistics', Statistics);
