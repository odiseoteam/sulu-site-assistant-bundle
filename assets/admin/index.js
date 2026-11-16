// @flow
import {viewRegistry} from 'sulu-admin-bundle/containers';
import Conversation from './views/Conversation';
import EditableItem from './views/EditableItem';
import Handoff from './views/Handoff';
import Statistics from './views/Statistics';

viewRegistry.add('odiseo_site_assistant.conversation', Conversation);
viewRegistry.add('odiseo_site_assistant.editable_item', EditableItem);
viewRegistry.add('odiseo_site_assistant.handoff', Handoff);
viewRegistry.add('odiseo_site_assistant.statistics', Statistics);
