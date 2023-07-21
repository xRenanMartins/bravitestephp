import { NgModule } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ContactComponent } from './pages/contact/contact.component';
import { PrimengModule } from 'src/app/shared/primeng.module';
import { ContactRoutingModule } from './contact-routing.module';



@NgModule({
  declarations: [
    ContactComponent,
  ],
  imports: [
    CommonModule,
    PrimengModule,
    ContactRoutingModule,
  ]
})
export class ContactModule { }
