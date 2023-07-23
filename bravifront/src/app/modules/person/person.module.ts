import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { PrimengModule } from 'src/app/shared/primeng.module';
import { PersonRoutingModule } from './person-routing.module';
import { PersonComponent } from './pages/person/person.component';
import { AddPersonComponent } from './components/add-person/add-person.component';
import { FormsModule, ReactiveFormsModule } from '@angular/forms';
import { ConfirmationService, MessageService } from 'primeng/api';
import { ListContactsComponent } from './components/list-contacts/list-contacts.component';
import { AddContactComponent } from './components/add-contact/add-contact.component';
import { NgxMaskModule } from 'ngx-mask';



@NgModule({
  declarations: [
    PersonComponent,
    AddPersonComponent,
    ListContactsComponent,
    AddContactComponent,
  ],
  imports: [
    CommonModule,
    PrimengModule,
    PersonRoutingModule,
    FormsModule,
    ReactiveFormsModule,
    NgxMaskModule.forRoot(),
  ],
  providers: [
    MessageService,
    ConfirmationService,
  ]
})
export class PersonModule { }
